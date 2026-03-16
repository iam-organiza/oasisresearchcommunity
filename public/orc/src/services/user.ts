import { AxiosError } from "axios";
import { instance, SuccessResponse } from "../config/axios.config";
import { IUser, setState } from "../store";

export async function getUserProfile() {
  try {
    const response = await instance.get<
      SuccessResponse<{
        user: IUser;
      }>
    >("api/user");

    setState("user", response.data.data.user);
  } catch (e) {
    const error = e as AxiosError<any>;
    if (error.response) {
      //   console.error("Error:", error.response.data.message);
    } else {
      //   console.error("Request failed:", error.message);
    }
  }
}
