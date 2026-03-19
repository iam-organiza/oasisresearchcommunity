import { AxiosError } from "axios";
import { instance, SuccessResponse } from "../config/axios.config";
import {
  getState,
  IBlogPost,
  PaginatedResponse,
  setState,
} from "../store";

export async function getBlogPosts({ page = 0, size = 10, search = "", admin = 1 }) {
  try {
    const response = await instance.get<
      SuccessResponse<PaginatedResponse<IBlogPost>>
    >("api/blog-posts", { params: { page, size, search, admin } });

    setState("blogPosts", response.data.data);
  } catch (e) {
    const error = e as AxiosError<any>;
    if (error.response) {
      //   console.error("Error:", error.response.data.message);
    } else {
      //   console.error("Request failed:", error.message);
    }
  }
}

export async function deleteBlogPost(postRef: string) {
  try {
    const response = await instance.delete<SuccessResponse<[]>>(
      "api/blog-posts/" + postRef
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

export async function handleDeleteBlogPost(postRef: string) {
  const success = await deleteBlogPost(postRef);
  if (!success) return;

  const current = getState().blogPosts;
  if (!current) return;

  const updatedResult = current.result.filter((p) => p.postRef !== postRef);

  setState("blogPosts", {
    ...current,
    result: updatedResult,
    totalElements: current.totalElements - 1,
    totalPages: Math.ceil((current.totalElements - 1) / current.size),
  });
  return true;
}
