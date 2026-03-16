import axios, { AxiosError, AxiosRequestConfig } from "axios";
import { Toast } from "bootstrap";
import { baseURL } from "../utils/helper.utils";

export type ErrorResponse = {
  success: boolean;
  message: string;
  errors: string[];
};

export type SuccessResponse<T> = {
  success: boolean;
  message: string;
  data: T;
};

export const instance = axios.create({
  baseURL,
  timeout: 60000,
  headers: {
    "Content-Type": "application/json",
  },
});

// Attach access token from storage (on init)
const accessToken = localStorage.getItem("accessToken");
if (accessToken) {
  instance.defaults.headers.common["Authorization"] = `Bearer ${accessToken}`;
}

// --- Token Refresh Handling ---

type FailedRequest = {
  resolve: (token: string) => void;
  reject: (error: any) => void;
};

let isRefreshing = false;
let failedQueue: FailedRequest[] = [];

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach(({ resolve, reject }) => {
    if (error) {
      reject(error);
    } else {
      resolve(token!);
    }
  });
  failedQueue = [];
};

// --- Request Interceptor ---

instance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("accessToken");
    if (token) {
      if (!config.headers) config.headers = {};
      (config.headers as Record<string, string>)[
        "Authorization"
      ] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// --- Response Interceptor ---

instance.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (!error.response) {
      // No response = network error
      return Promise.reject(error);
    }

    const originalRequest = error.config as AxiosRequestConfig & {
      _retry?: boolean;
    };

    if (error.response.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise<string>((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token: string) => {
            if (!originalRequest.headers) originalRequest.headers = {};
            (originalRequest.headers as Record<string, string>)[
              "Authorization"
            ] = `Bearer ${token}`;
            return instance(originalRequest);
          })
          .catch((err) => Promise.reject(err));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      const refreshToken = localStorage.getItem("refreshToken");
      if (!refreshToken) {
        isRefreshing = false;
        return Promise.reject(error);
      }

      try {
        const response = await axios.post<
          SuccessResponse<{ accessToken: string }>
        >(`${baseURL}/refresh-token`, { refreshToken });

        const newAccessToken = response.data.data.accessToken;
        localStorage.setItem("accessToken", newAccessToken);
        instance.defaults.headers.common[
          "Authorization"
        ] = `Bearer ${newAccessToken}`;
        processQueue(null, newAccessToken);
        return instance(originalRequest);
      } catch (err) {
        console.log(JSON.stringify(err));

        processQueue(err, null);
        localStorage.removeItem("accessToken");
        localStorage.removeItem("refreshToken");

        // Optional: show toast or delay before redirect
        const toastElement = $("#kt_docs_toast_toggle");
        const toast = Toast.getOrCreateInstance(toastElement[0], {
          // delay: 5000
        });

        toastElement
          .find(".toast-body")
          .html("Session expired. Redirecting to login...");

        toast.show();
        setTimeout(() => {
          toast.hide();
          window.location.href = `${baseURL}login`;
        }, 1000);

        return Promise.reject(err);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);
