import $ from "jquery";

import { AxiosError } from "axios";
import "block-ui";
import { jwtDecode } from "jwt-decode";
import { ErrorResponse, instance } from "../config/axios.config";
import { IUser, setState } from "../store";
import { showAlertBox, startLoading, stopLoading } from "../utils/form.utils";
import { baseURL, showToast } from "../utils/helper.utils";

type SigninResponse = {
  data: { accessToken: string; refreshToken: string };
  message: string;
  success: boolean;
};

export function handleSigninForm(event: SubmitEvent) {
  event.preventDefault();

  let form = event.currentTarget as HTMLFormElement;
  let submit_btn = $(form.members_sign_in_submit);

  const alertElement = $(form).find(".alert");
  var scrollTop = $(form)?.offset()?.top ?? 0;

  let email = $(form.email).val();
  let password = $(form.password).val();

  let payload = {
    email,
    password,
  };

  console.log("process.env", baseURL);

  startLoading(submit_btn);
  instance
    .post<SigninResponse>("api/login", payload)
    .then((response) => {
      if (response.data.success) {
        const data = response.data.data;
        const message = response.data.message;

        form.reset();
        $(window).animate({}).scrollTop(scrollTop);

        localStorage.setItem("accessToken", data.accessToken);
        localStorage.setItem("refreshToken", data.refreshToken);

        const decoded = jwtDecode(data.accessToken) as IUser;
        setState("user", {
          userId: decoded.userId,
          userRef: decoded.userRef,
          email: decoded.email,
          role: decoded.role,
          firstName: decoded.firstName,
          lastName: decoded.lastName,
        });

        instance.defaults.headers[
          "Authorization"
        ] = `Bearer ${data.accessToken}`;

        showToast(
          message,
          false,
          () => {
            window.location.href = `${baseURL}admin/`;
          },
          1000
        );
      }
    })
    .catch((error: AxiosError<ErrorResponse>) => {
      $(window).animate({}).scrollTop(scrollTop);
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
