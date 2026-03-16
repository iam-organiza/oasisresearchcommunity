// store.ts

import { jwtDecode } from "jwt-decode";

export interface PaginatedResponse<T> {
  result: T[];
  currentPage: number;
  size: number;
  totalElements: number;
  totalPages: number;
  first: boolean;
  last: boolean;
}

export type IUser = {
  userId: string;
  userRef: string;
  email: string;
  role: "admin" | "member";
  firstName: string;
  lastName: string;
};

export interface IFeaturedMember {
  id: number;
  memberRef: string;
  avatar: string;
  title: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  position: string;
  speciality: string;
  job_description: string;
  created_at: string;
}

type State = {
  user: IUser | null;
  featuredMembers: PaginatedResponse<IFeaturedMember> | null;
};

type StateKey = keyof State;

type StateChangeEvent<K extends StateKey = StateKey> = {
  key: K;
  value: State[K];
  oldValue: State[K];
  state: State;
};

const state: State = {
  user: null,
  featuredMembers: null,
};

export function initUserFromToken() {
  const token = localStorage.getItem("accessToken");
  console.log({ token });

  if (!token) return null;

  try {
    const decoded = jwtDecode<any>(token);
    setTimeout(() => {
      setState("user", {
        userId: decoded.userId,
        userRef: decoded.userRef,
        email: decoded.email,
        role: decoded.role,
        firstName: decoded.firstName,
        lastName: decoded.lastName,
      });
    }, 300);
  } catch (err) {
    console.warn("Invalid accessToken", err);
    return null;
  }
}

const storeEvents = new EventTarget();

export function getState(): State {
  return { ...state };
}

export function setState<K extends StateKey>(key: K, value: State[K]): void {
  const oldValue = state[key];
  state[key] = value;

  const event = new CustomEvent<StateChangeEvent<K>>("change", {
    detail: {
      key,
      value,
      oldValue,
      state: getState(),
    },
  });

  storeEvents.dispatchEvent(event);
}

export function subscribe<K extends StateKey>(
  callback: (change: StateChangeEvent<K>) => void
): () => void {
  const listener = (event: Event) => {
    callback((event as CustomEvent<StateChangeEvent<K>>).detail);
  };

  storeEvents.addEventListener("change", listener);
  return () => storeEvents.removeEventListener("change", listener);
}
