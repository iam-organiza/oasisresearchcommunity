import { FeaturedMemberFormData } from "../forms/featured.member";

export function startLoading(button: JQuery<HTMLElement>) {
  button.addClass("disabled");
  button.attr("data-kt-indicator", "on");
}

export function stopLoading(button: JQuery<HTMLElement>) {
  button.removeClass("disabled");
  button.attr("data-kt-indicator", "off");
}

export function showAlertBox(alertElement: JQuery<HTMLElement>) {
  alertElement.toggleClass("d-none");
  setTimeout(() => alertElement.toggleClass("d-none"), 5000);
}

// ----------------- Helpers (updated) -----------------
export function getInputValue(form: HTMLFormElement, name: string): string {
  return (
    (
      form.elements.namedItem(name) as
        | HTMLInputElement
        | HTMLSelectElement
        | HTMLTextAreaElement
    )?.value?.trim() || ""
  );
}

export function getKTImageInputInstance() {
  const el = document.getElementById("kt_featured_member_image_input");
  const instance = window.KTImageInput?.getInstance(el);
  const input = instance?.getInputElement() as HTMLInputElement | undefined;
  return { instance, input };
}

export function clearFeaturedMemberErrors(
  form: HTMLFormElement,
  requiredFields: string[]
) {
  requiredFields.forEach((fieldName) => {
    const input = form.elements.namedItem(fieldName) as
      | HTMLInputElement
      | HTMLSelectElement
      | HTMLTextAreaElement;

    const errorDiv = form.querySelector(
      `[data-error="${fieldName}"]`
    ) as HTMLElement;

    if (input) input.classList.remove("is-invalid");

    if (errorDiv) {
      errorDiv.textContent = "";
      errorDiv.classList.add("d-none");
    }
  });

  // Also clear avatar-specific validation state
  const { instance: imageInputInstance, input: avatarInput } =
    getKTImageInputInstance();

  const avatarError = form.querySelector(
    `[data-error="avatar"]`
  ) as HTMLElement;

  if (avatarInput) avatarInput.classList.remove("is-invalid");
  if (avatarError) {
    avatarError.textContent = "";
    avatarError.classList.add("d-none");
  }

  // NOTE: we do NOT call instance.cancelElement here because this function
  // is used to clear validation state *only*. If you want the UI cleared
  // (preview removed) call the cancelElement separately where appropriate.
}

export function validateRequiredFields(
  form: HTMLFormElement,
  required: string[]
): boolean {
  let isValid = true;

  required.forEach((field) => {
    const input = form.elements.namedItem(field) as any;
    const errorDiv = form.querySelector(
      `[data-error="${field}"]`
    ) as HTMLElement;

    if (!input?.value?.trim()) {
      isValid = false;
      input?.classList.add("is-invalid");
      if (errorDiv) {
        errorDiv.textContent = "This field is required.";
        errorDiv.classList.remove("d-none");
      }
    }
  });

  return isValid;
}

export function validateAvatar(form: HTMLFormElement, required: boolean) {
  const { instance: imageInputInstance, input: avatarInput } =
    getKTImageInputInstance();
  const file = avatarInput?.files?.[0];
  const errorDiv = form.querySelector(`[data-error="avatar"]`) as HTMLElement;

  avatarInput?.classList.remove("is-invalid");

  if (!required) {
    if (errorDiv) {
      errorDiv.textContent = "";
      errorDiv.classList.add("d-none");
    }
    return file;
  }

  if (!file) {
    avatarInput?.classList.add("is-invalid");
    if (errorDiv) {
      errorDiv.textContent = "Avatar is required.";
      errorDiv.classList.remove("d-none");
    }
    return null;
  }

  if (errorDiv) {
    errorDiv.textContent = "";
    errorDiv.classList.add("d-none");
  }

  return file;
}

export function buildFormData(data: FeaturedMemberFormData): FormData {
  const fd = new FormData();

  Object.entries(data).forEach(([key, value]) => {
    // Ensure we only append real values; avatar may be a File
    if (value !== undefined && value !== null && value !== "") {
      fd.append(key, value as any);
    }
  });

  return fd;
}

export function toggleSubmitLoading(btn: HTMLButtonElement, state: boolean) {
  if (state) {
    btn.setAttribute("data-kt-indicator", "on");
    btn.setAttribute("disabled", "on");
  } else {
    btn.removeAttribute("data-kt-indicator");
    btn.removeAttribute("disabled");
  }
}

export function clickKTImageInputCancelIfPresent() {
  const { instance: imageInputInstance } = getKTImageInputInstance();
  if (imageInputInstance) {
    const cancelElement = imageInputInstance.cancelElement as
      | HTMLElement
      | undefined;
    if (cancelElement) {
      // protective try/catch to avoid accidental runtime errors
      try {
        cancelElement.click();
      } catch (err) {
        // swallow - cancelElement click is best-effort
        // optionally console.warn(err);
      }
    }
  }
}
