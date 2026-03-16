import { baseURL } from "../utils/helper.utils";

export function handleSignout() {
  localStorage.removeItem("accessToken");
  localStorage.removeItem("refreshToken");

  window.location.href = `${baseURL}`;
}
