import { AxiosError } from "axios";
import { ErrorResponse, instance } from "../config/axios.config";
import { getState, IFeaturedMember, setState } from "../store";
import {
  buildFormData,
  clearFeaturedMemberErrors,
  clickKTImageInputCancelIfPresent,
  getInputValue,
  toggleSubmitLoading,
  validateAvatar,
  validateRequiredFields,
} from "../utils/form.utils";
import { showToast } from "../utils/helper.utils";

// Define the structure of your form data
export interface FeaturedMemberFormData {
  avatar?: File;
  title: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  position: string;
  speciality: string;
  job_description: string;
}

type AddFeaturedMemberResponse = {
  data: IFeaturedMember;
  message: string;
  success: boolean;
};

export function handleFeaturedMemberForm(e: Event) {
  e.preventDefault();

  const form = e.target as HTMLFormElement;
  const submitter = form.querySelector(
    'button[type="submit"]'
  ) as HTMLButtonElement;
  const scrollTop = $(form)?.offset()?.top ?? 0;

  const requiredFields: (keyof FeaturedMemberFormData)[] = [
    "avatar",
    "title",
    "first_name",
    "last_name",
    "position",
    "job_description",
  ];

  clearFeaturedMemberErrors(form, requiredFields);

  const fieldsValid = validateRequiredFields(form, requiredFields);
  const avatarFile = validateAvatar(form, true);

  if (!fieldsValid || !avatarFile) return;

  const payload: FeaturedMemberFormData = {
    avatar: avatarFile!,
    title: getInputValue(form, "title"),
    first_name: getInputValue(form, "first_name"),
    middle_name: getInputValue(form, "middle_name"),
    last_name: getInputValue(form, "last_name"),
    position: getInputValue(form, "position"),
    speciality: getInputValue(form, "speciality"),
    job_description: getInputValue(form, "job_description"),
  };

  const formData = buildFormData(payload);

  toggleSubmitLoading(submitter, true);

  instance
    .post<AddFeaturedMemberResponse>("api/featured-members", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    })
    .then((response) => {
      if (response.data.success) {
        const data = response.data.data;
        const message = response.data.message;

        // Close modal
        document.getElementById("kt_modal_new_featured_cancel")?.click();

        // Clear KTImageInput UI (important)
        clickKTImageInputCancelIfPresent();

        window.$("[name=title]").val("").trigger("change");
        form.reset();

        $(window).animate({}).scrollTop(scrollTop);
        showToast(message);

        const current = getState().featuredMembers;

        if (current) {
          const newResult = [data, ...current.result];
          const newTotalElements = current.totalElements + 1;
          const newTotalPages = Math.ceil(newTotalElements / current.size);

          setState("featuredMembers", {
            ...current,
            result: newResult,
            totalElements: newTotalElements,
            totalPages: newTotalPages,
            first: current.currentPage === 0,
            last: newTotalPages === current.currentPage + 1,
          });
        } else {
          setState("featuredMembers", {
            result: [data],
            currentPage: 1,
            size: 1,
            totalElements: 1,
            totalPages: 1,
            first: true,
            last: true,
          });
        }
      }
    })
    .catch((error: AxiosError<ErrorResponse>) => {
      $(window).animate({}).scrollTop(scrollTop);
      if (error.response?.status === 404) {
        showToast(`${error.message}`, true);
      } else {
        showToast(`${error.response?.data?.message || error.message}`, true);
      }
    })
    .finally(() => {
      toggleSubmitLoading(submitter, false);
    });
}

export function handleEditFeaturedMemberForm(e: Event) {
  e.preventDefault();

  const form = e.target as HTMLFormElement;
  const submitter = form.querySelector(
    'button[type="submit"]'
  ) as HTMLButtonElement;
  const scrollTop = $(form)?.offset()?.top ?? 0;

  const requiredFields: (keyof FeaturedMemberFormData)[] = [
    "title",
    "first_name",
    "last_name",
    "position",
    "job_description",
  ];

  clearFeaturedMemberErrors(form, ["avatar", ...requiredFields]);

  const fieldsValid = validateRequiredFields(form, requiredFields);
  const avatarFile = validateAvatar(form, false); // avatar optional on edit

  if (!fieldsValid) return;

  const memberRef = $(form).find("[name='memberRef']").val();
  if (!memberRef) {
    showToast("Invalid reference");
    return;
  }

  const payload: FeaturedMemberFormData = {
    avatar: avatarFile || undefined,
    title: getInputValue(form, "title"),
    first_name: getInputValue(form, "first_name"),
    middle_name: getInputValue(form, "middle_name"),
    last_name: getInputValue(form, "last_name"),
    position: getInputValue(form, "position"),
    speciality: getInputValue(form, "speciality"),
    job_description: getInputValue(form, "job_description"),
  };

  const formData = buildFormData(payload);

  toggleSubmitLoading(submitter, true);

  instance
    .put<any>(`api/featured-members/${memberRef}`, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    })
    .then((response) => {
      if (response.data.success) {
        const data = response.data.data;
        const message = response.data.message;

        // Close modal
        document.getElementById("kt_modal_new_featured_cancel")?.click();

        // If an avatar was present and you want to clear the preview after edit,
        // call cancel. (Original code only cleared on add; keep same behavior if desired.)
        clickKTImageInputCancelIfPresent();

        $(window).animate({}).scrollTop(scrollTop);
        showToast(message);

        const current = getState().featuredMembers;
        if (current) {
          const updatedResult = current.result.map((m: IFeaturedMember) =>
            m.memberRef === data.memberRef ? data : m
          );

          setState("featuredMembers", { ...current, result: updatedResult });
        }
      }
    })
    .catch((error: AxiosError<ErrorResponse>) => {
      $(window).animate({}).scrollTop(scrollTop);
      if (error.response?.status === 404) {
        showToast(`${error.message}`, true);
      } else {
        showToast(`${error.response?.data?.message || error.message}`, true);
      }
    })
    .finally(() => {
      toggleSubmitLoading(submitter, false);
    });
}
