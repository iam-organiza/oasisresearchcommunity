import { AxiosError } from "axios";
import {
  ErrorResponse,
  instance,
  SuccessResponse,
} from "../config/axios.config";
import { showAlertBox, startLoading, stopLoading } from "../utils/form.utils";
import { baseURL, showToast } from "../utils/helper.utils";

export function verifyOtp(
  e: Event,
  data: {
    email: string;
    message: string;
  } | null
) {
  e.preventDefault();

  let form = e.currentTarget as HTMLFormElement;
  let submit_btn = $(form.kt_verification_submit_button);

  const alertElement = $(form).find(".alert");

  const digits = [];
  for (let i = 1; i <= 6; i++) {
    const input = document.getElementById(`code_${i}`) as HTMLInputElement;
    if (input && input.value.trim() !== "") {
      digits.push(input.value.trim());
    } else {
      alert("Please fill in all 6 digits.");
      return;
    }
  }

  const otp = digits.join("");
  const payload = {
    otp,
    email: data?.email ?? "",
  };

  startLoading(submit_btn);
  instance
    .post<SuccessResponse<any>>("api/verify-otp", payload)
    .then((response) => {
      if (response.data.success) {
        const data = response.data.data;
        const message = response.data.message;

        form.reset();

        showToast(
          message,
          false,
          () => {
            window.location.href = `${baseURL}login/`;
          },
          2000
        );
      }
    })
    .catch((error: AxiosError<ErrorResponse>) => {
      if (error.response?.status === 404) {
        $(alertElement).find(".alert-content").html(`${error.message}`);
      } else {
        $(alertElement)
          .find(".alert-content")
          .html(`${error.response?.data.message}`);
      }

      showAlertBox(alertElement);
    })
    .finally(() => {
      stopLoading(submit_btn);
    });
}
