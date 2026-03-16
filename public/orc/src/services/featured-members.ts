import { AxiosError } from "axios";
import { instance, SuccessResponse } from "../config/axios.config";
import {
  getState,
  IFeaturedMember,
  PaginatedResponse,
  setState,
} from "../store";

export async function getFeaturedMembers({ page = 0, size = 10, search = "" }) {
  try {
    const response = await instance.get<
      SuccessResponse<PaginatedResponse<IFeaturedMember>>
    >("api/featured-members", { params: { page, size, search } });

    setState("featuredMembers", response.data.data);
  } catch (e) {
    const error = e as AxiosError<any>;
    if (error.response) {
      //   console.error("Error:", error.response.data.message);
    } else {
      //   console.error("Request failed:", error.message);
    }
  }
}

export async function getFeaturedMember(memberRef: string) {
  try {
    const response = await instance.get<
      SuccessResponse<{ member: IFeaturedMember }>
    >("api/featured-members/" + memberRef);

    return response.data.data.member;
  } catch (e) {
    const error = e as AxiosError<any>;
    if (error.response) {
      //   console.error("Error:", error.response.data.message);
    } else {
      //   console.error("Request failed:", error.message);
    }
  }
}

export async function deleteFeaturedMember(memberRef: string) {
  try {
    const response = await instance.delete<SuccessResponse<[]>>(
      "api/featured-members/" + memberRef
    );

    return response.data.success;
  } catch (e) {
    const error = e as AxiosError<any>;
    if (error.response) {
      //   console.error("Error:", error.response.data.message);
    } else {
      //   console.error("Request failed:", error.message);
    }
  }
}

export async function handleDelete(memberRef: string) {
  const success = await deleteFeaturedMember(memberRef);
  if (!success) return;

  const current = getState().featuredMembers;
  if (!current) return;

  const updatedResult = current.result.filter((m) => m.memberRef !== memberRef);

  setState("featuredMembers", {
    ...current,
    result: updatedResult,
    totalElements: current.totalElements - 1,
    totalPages: Math.ceil((current.totalElements - 1) / current.size),
  });
  return true;
}
