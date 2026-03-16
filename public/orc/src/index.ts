import "block-ui";
import $ from "jquery";
import { instance, SuccessResponse } from "./config/axios.config";
import { handleRegisterMemberForm } from "./forms/register";
import { handleSigninForm } from "./forms/signin";
import { verifyOtp } from "./forms/verify.otp";
import { getFeaturedMembers } from "./services/featured-members";
import { getUserProfile } from "./services/user";
import { IFeaturedMember, subscribe } from "./store";
import { redirectIfAuthenticated } from "./utils/auth.utils";
import { showAlertBox } from "./utils/form.utils";
import { baseURL, decodePayload, showToast } from "./utils/helper.utils";

console.log("baseURL", baseURL);

function initTinySliderForElement(el: any) {
  if (!el || el.getAttribute("data-kt-initialized") === "1") return;

  const config: any = {};
  el.getAttributeNames().forEach((attr: any) => {
    if (/^data-tns-.*/.test(attr)) {
      let key = attr
        .replace("data-tns-", "")
        .toLowerCase()
        .replace(/(?:[\s-])\w/g, (match: any) =>
          match.replace("-", "").toUpperCase()
        );

      let value = el.getAttribute(attr);

      if (attr === "data-tns-responsive") {
        try {
          value = value.replace(
            /(\w+:)|(\w+ :)/g,
            (match: any) => `"${match.trim().slice(0, -1)}":`
          );
          config[key] = JSON.parse(value);
        } catch (e) {
          console.warn("Invalid responsive JSON:", value);
        }
      } else {
        config[key] =
          value === "true" ? true : value === "false" ? false : value;
      }
    }
  });

  const options = Object.assign(
    {},
    {
      container: el,
      slideBy: "page",
      autoplay: true,
      autoplayButtonOutput: false,
    },
    config
  );

  if (el.closest(".tns")) {
    el.closest(".tns").classList.add("tns-initiazlied");
  }

  window.tns(options);
  el.setAttribute("data-kt-initialized", "1");
}

const buildFeaturedMembersHTML = (member: IFeaturedMember) => `

  <!--begin::Item-->
  <div class="text-center">
    <!--begin::Photo-->
    <div class="octagon mx-auto mb-5 d-flex w-200px h-200px bgi-no-repeat bgi-size-contain bgi-position-center"
        style="background-image:url('/${
          member.avatar
        }');background-position: top;background-size: cover;">
    </div>
    <!--end::Photo-->
    <!--begin::Person-->
    <div class="mb-0">
        <!--begin::Name-->
        <a href="#" class="text-dark fw-bold text-hover-primary fs-3 poppins-regular">${
          member.title
        } ${member.first_name} ${member.middle_name ?? ""} ${
  member.last_name
} <sub>(${member.position})</sub></a>
        <!--end::Name-->
        <!--begin::Position-->
        <div class="text-muted fs-6 fw-semibold mt-1">
            ${member.speciality && `${member.speciality} <br>`}
              ${member.job_description}
        </div>
        <!--begin::Position-->
    </div>
    <!--end::Person-->
  </div>
  <!--end::Item-->
`;

const renderFeaturedMembers = (data: IFeaturedMember[]) => {
  const div = document.querySelector(
    ".tns-default-featured-members"
  ) as HTMLDivElement;

  if (div) {
    console.log(div);
    div.innerHTML = `
    <div class="tns tns-default">
      <div data-tns="true" data-tns-loop="true" data-tns-swipe-angle="false" data-tns-speed="2000"
        data-tns-autoplay="true" data-tns-autoplay-timeout="18000" data-tns-controls="true"
        data-tns-nav="false" data-tns-items="1" data-tns-center="false" data-tns-dots="false"
        data-tns-prev-button="#kt_team_slider_prev" data-tns-next-button="#kt_team_slider_next"
        data-tns-responsive="{1200: {items: 3}, 992: {items: 2}}">
      ${data.map(buildFeaturedMembersHTML).join("")}
      </div>

      <!--begin::Button-->
      <button class="btn btn-icon btn-active-color-primary" id="kt_team_slider_prev">
          <!--begin::Svg Icon | path: icons/duotune/arrows/arr074.svg-->
          <span class="svg-icon svg-icon-3x">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path
                      d="M11.2657 11.4343L15.45 7.25C15.8642 6.83579 15.8642 6.16421 15.45 5.75C15.0358 5.33579 14.3642 5.33579 13.95 5.75L8.40712 11.2929C8.01659 11.6834 8.01659 12.3166 8.40712 12.7071L13.95 18.25C14.3642 18.6642 15.0358 18.6642 15.45 18.25C15.8642 17.8358 15.8642 17.1642 15.45 16.75L11.2657 12.5657C10.9533 12.2533 10.9533 11.7467 11.2657 11.4343Z"
                      fill="currentColor" />
              </svg>
          </span>
          <!--end::Svg Icon-->
      </button>
      <!--end::Button-->
      <!--begin::Button-->
      <button class="btn btn-icon btn-active-color-primary" id="kt_team_slider_next">
          <!--begin::Svg Icon | path: icons/duotune/arrows/arr071.svg-->
          <span class="svg-icon svg-icon-3x">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path
                      d="M12.6343 12.5657L8.45001 16.75C8.0358 17.1642 8.0358 17.8358 8.45001 18.25C8.86423 18.6642 9.5358 18.6642 9.95001 18.25L15.4929 12.7071C15.8834 12.3166 15.8834 11.6834 15.4929 11.2929L9.95001 5.75C9.5358 5.33579 8.86423 5.33579 8.45001 5.75C8.0358 6.16421 8.0358 6.83579 8.45001 7.25L12.6343 11.4343C12.9467 11.7467 12.9467 12.2533 12.6343 12.5657Z"
                      fill="currentColor" />
              </svg>
          </span>
          <!--end::Svg Icon-->
      </button>
      <!--end::Button-->
    </div>
    `;

    const newSlider = document.querySelector(
      '[data-tns="true"]:not([data-kt-initialized])'
    );
    if (newSlider) {
      initTinySliderForElement(newSlider);
    }
  }
};

(async function () {
  // Redirect if already logged in
  redirectIfAuthenticated();

  // Attempt to fetch user profile
  getUserProfile();

  const signinLink = document.querySelector(
    ".signin-link"
  ) as HTMLDivElement | null;

  if (signinLink) {
    signinLink.innerHTML = `<a href="/signin/" class="btn bg-secondary text-white">Sign In</a>`;
  }

  getFeaturedMembers({});

  // Update the link to Dashboard if user state changes
  subscribe(({ key, state }) => {
    const user = state.user;
    const featuredMembers = state.featuredMembers;
    if (key === "user" && user && signinLink) {
      signinLink.innerHTML = `<a href="/admin/" class="btn bg-secondary text-white">Dashboard</a>`;
    }

    if (key === "featuredMembers" && featuredMembers)
      renderFeaturedMembers(featuredMembers.result);
  });

  // Attach sign-in form handler if form exists
  document
    .getElementById("members_sign_in_form")
    ?.addEventListener("submit", handleSigninForm);

  const registerForm = document.getElementById(
    "members_sign_up_form"
  ) as HTMLFormElement;

  if (registerForm) {
    registerForm.addEventListener("submit", handleRegisterMemberForm);
  }

  const currentUrl = window.location.pathname;

  if (currentUrl === "/verify/") {
    const payload = new URLSearchParams(window.location.search).get("p");

    if (payload) {
      const search = new URLSearchParams(window.location.search);
      const encodedPayload = search.get("p");

      if (encodedPayload) {
        const data = await decodePayload<{ email: string; message: string }>(
          encodedPayload
        );

        if (!data) {
          window.location.href = `${baseURL}login/`;
          return;
        }

        const form = $("#kt_verification_form");
        const alertEl = $(form).find("#alert-message");

        if (alertEl) {
          $(alertEl).html(data?.message ?? "OTP has been sent");
        }

        form.on("submit", (e) => {
          verifyOtp(e as unknown as Event, data);
        });

        const resendBtn = $("#resend_otp");

        var target = document.querySelector("#kt_body");

        $(resendBtn).on("click", (e) => {
          const alertElement = $(form).find(".alert");

          const existingInstance = window.KTBlockUI.getInstance(target);
          if (existingInstance) {
            existingInstance.destroy(); // this removes it from KTUtil.data
          }

          const blockUI = new window.KTBlockUI(target);

          blockUI.block({
            message: `<span class="indicator-progress text-white d-block">
                    sending... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>`,
          });
          instance
            .post<SuccessResponse<any>>("/api/resend-otp", {
              email: data?.email ?? "",
            })
            .then((response) => {
              const data = response.data;
              const message = data.message;

              console.log({ data });

              showToast(message, false, () => {}, 5000);
            })
            .catch((error) => {
              console.log(alertElement);

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
              blockUI.release();
            });
        });
      } else {
        console.log("Missing payload. Please register first.");
      }
    } else {
      window.location.href = `${baseURL}login/`;
    }
  }
})();
