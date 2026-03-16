import { Toast } from "bootstrap";

export const baseURL = process.env.baseURL;

export function getInitials(fullName: string): string {
  if (!fullName.trim()) return "";

  const parts = fullName
    .trim()
    .split(/\s+/) // split by any whitespace
    .filter(Boolean);

  const initials = parts.slice(0, 2).map((name) => name[0].toUpperCase());

  return initials.join("");
}

export function prettifyDate(timestamp: string | number | Date): string {
  const date = new Date(timestamp);

  if (isNaN(date.getTime())) {
    return "Invalid date";
  }

  const options: Intl.DateTimeFormatOptions = {
    year: "numeric",
    month: "long", // or 'short'
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  };

  return date.toLocaleString(undefined, options);
}

export const showToast = (
  message: string,
  isError = false,
  cb?: () => void,
  duration = 3000
) => {
  const toastElement = $("#kt_docs_toast_toggle");
  if (!toastElement) return;

  const toast = Toast.getOrCreateInstance(toastElement[0]);
  toastElement
    .toggleClass("bg-danger text-white", isError)
    .find(".toast-body")
    .html(message);
  toast.show();
  setTimeout(() => toast.hide(), isError ? 5000 : duration);
  toastElement[0].addEventListener("hidden.bs.toast", () => {
    toastElement.removeClass("bg-danger text-white");
    cb?.();
  });
};

export function validateEmail(email: string): boolean {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email.toLowerCase());
}

function blobToBase64(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => {
      const result = reader.result as string;
      const base64 = result.split(",")[1]; // remove "data:application/octet-stream;base64,"
      resolve(base64);
    };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

function base64ToBytes(base64: string): Promise<Uint8Array> {
  return fetch(`data:application/octet-stream;base64,${base64}`)
    .then((res) => res.arrayBuffer())
    .then((buf) => new Uint8Array(buf));
}

export async function encodePayload(payload: object): Promise<string> {
  const jsonStr = JSON.stringify(payload);
  const bytes = new TextEncoder().encode(jsonStr);
  const blob = new Blob([bytes]);
  const base64 = await blobToBase64(blob);
  return encodeURIComponent(base64);
}

export async function decodePayload<T>(encoded: string): Promise<T | null> {
  try {
    const base64 = decodeURIComponent(encoded);
    const bytes = await base64ToBytes(base64);
    const jsonStr = new TextDecoder().decode(bytes);
    return JSON.parse(jsonStr);
  } catch (error) {
    console.error("Failed to decode payload:", error);
    return null;
  }
}
