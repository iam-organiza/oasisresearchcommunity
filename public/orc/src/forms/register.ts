import { AxiosError } from "axios";
import { ErrorResponse, instance } from "../config/axios.config";
import { showAlertBox, startLoading, stopLoading } from "../utils/form.utils";
import { baseURL, encodePayload, validateEmail } from "../utils/helper.utils";

export type RegisterPayload = {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
};

type RegisterResponse = {
  data: { email: string };
  message: string;
  success: boolean;
};

export async function handleRegisterMemberForm(e: Event) {
  e.preventDefault();

  let form = e.currentTarget as HTMLFormElement;
  let submit_btn = $(form.members_sign_up_submit);

  const alertElement = $(form).find(".alert");
  var scrollTop = $(form)?.offset()?.top ?? 0;

  const formData = new FormData(form);

  const firstName = formData.get("firstName")?.toString().trim() || "";
  const lastName = formData.get("lastName")?.toString().trim() || "";
  const email = formData.get("email")?.toString().trim() || "";
  const password = formData.get("password")?.toString() || "";
  const confirmPassword = formData.get("confirm_password")?.toString() || "";
  const acceptedTerms = formData.get("terms_and_conditions") === "1";

  const errors: string[] = [];

  // Basic validations
  if (!firstName || !lastName || !email || !password || !confirmPassword) {
    errors.push("All fields are required.");
  }

  if (!validateEmail(email)) {
    errors.push("Invalid email format.");
  }

  if (password.length < 8) {
    errors.push("Password must be at least 8 characters.");
  }

  if (password !== confirmPassword) {
    errors.push("Passwords do not match.");
  }

  if (!acceptedTerms) {
    errors.push("You must accept the Terms and Conditions.");
  }

  if (errors.length > 0) {
    $(alertElement).find(".alert-content").html(errors.join("<br>"));
    showAlertBox(alertElement);
    return;
  }

  const payload: RegisterPayload = {
    firstName,
    lastName,
    email,
    password,
  };

  startLoading(submit_btn);
  instance
    .post<RegisterResponse>("/api/register", payload)
    .then(async (response) => {
      if (response.data.success) {
        const data = response.data.data;
        const message = response.data.message;

        form.reset();
        $(window).animate({}).scrollTop(scrollTop);

        const payloadObj = { email: data.email, message };
        const encoded = await encodePayload(payloadObj);
        window.location.href = `${baseURL}verify/?p=${encoded}`;
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
