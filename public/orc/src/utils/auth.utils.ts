import {baseURL} from "./helper.utils";

export function redirectIfAuthenticated() {
  const accessToken = localStorage.getItem("accessToken");
  const currentPath = window.location.pathname;

  const isOnAdminPage = currentPath.startsWith("/admin");
  const isOnHomePage = currentPath === "/";
  const isOnBlogPostPage = currentPath === "/blog-post.html" || currentPath === "/blog-post"; // Account for router rewrites
  const isOnBlogHomePage = currentPath === "/blog-home.html" || currentPath === "/blog-home"; // Account for router rewrites
  
  // Exclude pages that authenticated users should still be able to visit
  if (accessToken && !isOnAdminPage && !isOnHomePage && !isOnBlogPostPage && !isOnBlogHomePage) {
    window.location.href = `${baseURL}admin/`;
  }
}

export function redirectIfNotAuthenticated() {
  const accessToken = localStorage.getItem("accessToken");
  const currentPath = window.location.pathname;

  const isOnAdminPage = currentPath.startsWith("/admin");

  if (!accessToken && isOnAdminPage) {
    window.location.href = `${baseURL}`;
  }
}
