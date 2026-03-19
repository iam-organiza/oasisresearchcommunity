import "block-ui";
import $ from "jquery";
import moment from "moment";
import {
  FeaturedMemberFormData,
  handleEditFeaturedMemberForm,
  handleFeaturedMemberForm,
} from "./forms/featured.member";
import {
  bindSlugGenerator,
  handleBlogPostForm,
  handleCategoryForm,
  handleUpdateBlogPostForm,
  fetchBlogPostByRef,
  fetchCategories
} from "./forms/blog-post";
import { initBlogList, renderBlogPosts } from "./forms/blog-list";
import { handleSignout } from "./links/signout";
import {
  getFeaturedMember,
  getFeaturedMembers,
  handleDelete,
} from "./services/featured-members";
import { getUserProfile } from "./services/user";
import { IFeaturedMember, IUser, PaginatedResponse, subscribe } from "./store";
import { redirectIfNotAuthenticated } from "./utils/auth.utils";
import { clearFeaturedMemberErrors } from "./utils/form.utils";
import {
  baseURL,
  getInitials,
  prettifyDate,
  showToast,
} from "./utils/helper.utils";

declare global {
  interface Window {
    moment: any;
    Tagify: any;
    flatpickr: any;
    CKEditor: any;
    DecoupledEditor: any;
  }
}

let storeUnsubscribe: (() => void) | null = null;

function setFormToCreateMode(formEl: HTMLElement | null) {
  formEl?.removeEventListener("submit", handleEditFeaturedMemberForm);
  formEl?.addEventListener("submit", handleFeaturedMemberForm);
}

function setFormToEditMode(formEl: HTMLElement | null) {
  formEl?.removeEventListener("submit", handleFeaturedMemberForm);
  formEl?.addEventListener("submit", handleEditFeaturedMemberForm);
}

const updateUserLinksUI = (user: IUser) => {
  const div = document.querySelector(".dynamic-menu-links");

  if (div && user.role === "admin") {
    const html = `
      <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <!--begin:Menu link-->
        <span class="menu-link">
            <span class="menu-icon">
                <!--begin::Svg Icon | path: icons/duotune/abstract/abs042.svg-->
                <span class="svg-icon svg-icon-2">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M18 21.6C16.6 20.4 9.1 20.3 6.3 21.2C5.7 21.4 5.1 21.2 4.7 20.8L2 18C4.2 15.8 10.8 15.1 15.8 15.8C16.2 18.3 17 20.5 18 21.6ZM18.8 2.8C18.4 2.4 17.8 2.20001 17.2 2.40001C14.4 3.30001 6.9 3.2 5.5 2C6.8 3.3 7.4 5.5 7.7 7.7C9 7.9 10.3 8 11.7 8C15.8 8 19.8 7.2 21.5 5.5L18.8 2.8Z"
                            fill="currentColor"></path>
                        <path opacity="0.3"
                            d="M21.2 17.3C21.4 17.9 21.2 18.5 20.8 18.9L18 21.6C15.8 19.4 15.1 12.8 15.8 7.8C18.3 7.4 20.4 6.70001 21.5 5.60001C20.4 7.00001 20.2 14.5 21.2 17.3ZM8 11.7C8 9 7.7 4.2 5.5 2L2.8 4.8C2.4 5.2 2.2 5.80001 2.4 6.40001C2.7 7.40001 3.00001 9.2 3.10001 11.7C3.10001 15.5 2.40001 17.6 2.10001 18C3.20001 16.9 5.3 16.2 7.8 15.8C8 14.2 8 12.7 8 11.7Z"
                            fill="currentColor"></path>
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </span>
            <span class="menu-title">Landing Page</span>
            <span class="menu-arrow"></span>
        </span>
        <!--end:Menu link-->
        <!--begin:Menu sub-->
        <div class="menu-sub menu-sub-accordion">
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link" href="/admin/featured-members/">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Featured Members</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
        </div>
        <!--end:Menu sub-->
      </div>

      <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
        <!--begin:Menu link-->
        <span class="menu-link">
            <span class="menu-icon">
                <!--begin::Svg Icon | path: /var/www/preview.keenthemes.com/keenthemes/metronic/docs/core/html/src/media/icons/duotune/text/txt001.svg-->
                <span class="svg-icon svg-icon-2">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 11H3C2.4 11 2 10.6 2 10V9C2 8.4 2.4 8 3 8H13C13.6 8 14 8.4 14 9V10C14 10.6 13.6 11 13 11ZM22 5V4C22 3.4 21.6 3 21 3H3C2.4 3 2 3.4 2 4V5C2 5.6 2.4 6 3 6H21C21.6 6 22 5.6 22 5Z" fill="currentColor"/>
                    <path opacity="0.3" d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z" fill="currentColor"/>
                  </svg>
                </span>
                <!--end::Svg Icon-->
            </span>
            <span class="menu-title">Blog</span>
            <span class="menu-arrow"></span>
        </span>
        <!--end:Menu link-->
        <!--begin:Menu sub-->
        <div class="menu-sub menu-sub-accordion">
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link" href="/admin/blog/list-blog-posts/">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">List Blog Posts</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
            <!--begin:Menu item-->
            <div class="menu-item">
                <!--begin:Menu link-->
                <a class="menu-link" href="/admin/blog/create-blog-post/">
                    <span class="menu-bullet">
                        <span class="bullet bullet-dot"></span>
                    </span>
                    <span class="menu-title">Create Blog Post</span>
                </a>
                <!--end:Menu link-->
            </div>
            <!--end:Menu item-->
        </div>
        <!--end:Menu sub-->
      </div>
    `;
    div.innerHTML = html;
    updateSidebarActiveState();
  }
};

const updateSidebarActiveState = () => {
  const pathname = window.location.pathname;
  const menuLinks = document.querySelectorAll("#kt_app_sidebar_menu .menu-link");

  menuLinks.forEach((link) => {
    const href = (link as HTMLAnchorElement).getAttribute("href");
    if (href && (pathname === href || pathname === href + "/")) {
      link.classList.add("active");

      // Highlight parent items (accordions)
      let parent = link.parentElement;
      while (parent && parent.id !== "kt_app_sidebar_menu") {
        if (
          parent.classList.contains("menu-item") &&
          parent.classList.contains("menu-accordion")
        ) {
          parent.classList.add("here", "show");
        }
        parent = parent.parentElement;
      }
    }
  });
};

const updateUserUI = (user: IUser) => {
  document
    .querySelectorAll(".user-initials")
    .forEach(
      (el) =>
        (el.innerHTML = getInitials(`${user.firstName} ${user.lastName}`)),
    );
  document
    .querySelectorAll(".user-fullname")
    .forEach((el) => (el.innerHTML = `${user.firstName} ${user.lastName}`));
  document
    .querySelectorAll(".user-email")
    .forEach((el) => (el.innerHTML = user.email ?? ""));
};

const buildCardHTML = (member: IFeaturedMember) => `
  <div class="col-md-6 col-xxl-4">
    <div class="card">
      <div class="card-body d-flex flex-center flex-column pt-12 p-9 position-relative">
        <a href="javascript:void(0);" data-member-ref="${
          member.memberRef
        }" class="delete-featured-member fs-4 text-gray-800 text-hover-primary fw-bold mb-0 text-center position-absolute" style="top: 10px; right: 10px;">
          <span class="svg-icon svg-icon-muted svg-icon-2hx">
            <span class="svg-icon svg-icon-muted svg-icon-2hx">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 9C5 8.44772 5.44772 8 6 8H18C18.5523 8 19 8.44772 19 9V18C19 19.6569 17.6569 21 16 21H8C6.34315 21 5 19.6569 5 18V9Z" fill="currentColor"/>
                <path opacity="0.5" d="M5 5C5 4.44772 5.44772 4 6 4H18C18.5523 4 19 4.44772 19 5V5C19 5.55228 18.5523 6 18 6H6C5.44772 6 5 5.55228 5 5V5Z" fill="currentColor"/>
                <path opacity="0.5" d="M9 4C9 3.44772 9.44772 3 10 3H14C14.5523 3 15 3.44772 15 4V4H9V4Z" fill="currentColor"/>
              </svg>
            </span>
          </span>
        </a>
        <a href="javascript:void(0);" data-member-ref="${
          member.memberRef
        }" class="edit-featured-member fs-4 text-gray-800 text-hover-primary fw-bold mb-0 text-center position-absolute" style="top: 10px; left: 10px;">
          <span class="svg-icon svg-icon-muted svg-icon-2hx">
            <span class="svg-icon svg-icon-muted svg-icon-2hx">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.2869 3.15178L14.3601 4.07866L5.83882 12.5999L5.83881 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021L2.05445 20.6042C1.92743 20.9852 2.0266 21.4053 2.31063 21.6894C2.59466 21.9734 3.01478 22.0726 3.39584 21.9456L4.19792 21.6782L7.47918 20.5844L7.47919 20.5844C8.25353 20.3263 8.6407 20.1973 9.00498 20.0237C9.43469 19.8189 9.84082 19.5679 10.2162 19.2751C10.5344 19.0269 10.8229 18.7383 11.4001 18.1612L11.4001 18.1612L19.9213 9.63993L20.8482 8.71306C22.3839 7.17735 22.3839 4.68748 20.8482 3.15178C19.3125 1.61607 16.8226 1.61607 15.2869 3.15178Z" stroke="currentColor" stroke-width="1.5"/>
                <path opacity="0.5" d="M14.36 4.07812C14.36 4.07812 14.4759 6.04774 16.2138 7.78564C17.9517 9.52354 19.9213 9.6394 19.9213 9.6394M4.19789 21.6777L2.32178 19.8015" stroke="currentColor" stroke-width="1.5"/>
              </svg>
            </span>
          </span>
        </a>
        <div class="symbol symbol-100px symbol-circle mb-5"><img src="/${
          member.avatar
        }" style="object-fit: cover;" alt="image"></div>
        <a href="javascript:void(0);" class="fs-4 text-gray-800 text-hover-primary fw-bold mb-0 text-center">${
          member.title
        } ${member.first_name} ${member.middle_name ?? ""} ${
          member.last_name
        }</a>
        <div class="fw-semibold text-gray-400 mb-6 text-center">${
          member.speciality
        } (${member.position})</div>
        <div class="d-flex flex-center flex-wrap text-center">${
          member.job_description
        }</div>
      </div>
    </div>
  </div>
`;

const buildTableHTML = (member: IFeaturedMember, index: number) => `
  <tr class="${index % 2 === 0 ? "odd" : "even"}">
    <td>
      <div class="d-flex align-items-center">
        <div class="me-5 position-relative">
          <div class="symbol symbol-65px">
            <img alt="Pic" src="/${member.avatar}" style="object-fit: cover;">
          </div>
        </div>
        <div class="d-flex flex-column justify-content-center">
          <a href="" class="mb-1 text-gray-800 text-hover-primary">${
            member.title
          } ${member.first_name} ${member.middle_name ?? ""} ${
            member.last_name
          }</a>
        </div>
      </div>
    </td>
    <td>${member.position}</td>
    <td>${member.speciality}</td>
    <td>${member.job_description}</td>
    <td class="text-end">${prettifyDate(member.created_at)}</td>
    <td class="text-end">
      <div class="d-flex gap-5">
        <a href="javascript:void(0);" data-member-ref="${
          member.memberRef
        }" class="delete-featured-member fs-4 text-gray-800 text-hover-primary fw-bold mb-0 text-center">
          <span class="svg-icon svg-icon-muted svg-icon-2hx">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 9C5 8.44772 5.44772 8 6 8H18C18.5523 8 19 8.44772 19 9V18C19 19.6569 17.6569 21 16 21H8C6.34315 21 5 19.6569 5 18V9Z" fill="currentColor"/>
              <path opacity="0.5" d="M5 5C5 4.44772 5.44772 4 6 4H18C18.5523 4 19 4.44772 19 5V5C19 5.55228 18.5523 6 18 6H6C5.44772 6 5 5.55228 5 5V5Z" fill="currentColor"/>
              <path opacity="0.5" d="M9 4C9 3.44772 9.44772 3 10 3H14C14.5523 3 15 3.44772 15 4V4H9V4Z" fill="currentColor"/>
            </svg>
          </span>
        </a>
        <a href="javascript:void(0);" data-member-ref="${
          member.memberRef
        }" class="edit-featured-member fs-4 text-gray-800 text-hover-primary fw-bold mb-0 text-center">
          <span class="svg-icon svg-icon-muted svg-icon-2hx">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.2869 3.15178L14.3601 4.07866L5.83882 12.5999L5.83881 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021L2.05445 20.6042C1.92743 20.9852 2.0266 21.4053 2.31063 21.6894C2.59466 21.9734 3.01478 22.0726 3.39584 21.9456L4.19792 21.6782L7.47918 20.5844L7.47919 20.5844C8.25353 20.3263 8.6407 20.1973 9.00498 20.0237C9.43469 19.8189 9.84082 19.5679 10.2162 19.2751C10.5344 19.0269 10.8229 18.7383 11.4001 18.1612L11.4001 18.1612L19.9213 9.63993L20.8482 8.71306C22.3839 7.17735 22.3839 4.68748 20.8482 3.15178C19.3125 1.61607 16.8226 1.61607 15.2869 3.15178Z" stroke="currentColor" stroke-width="1.5"/>
                <path opacity="0.5" d="M14.36 4.07812C14.36 4.07812 14.4759 6.04774 16.2138 7.78564C17.9517 9.52354 19.9213 9.6394 19.9213 9.6394M4.19789 21.6777L2.32178 19.8015" stroke="currentColor" stroke-width="1.5"/>
              </svg>
          </span>
        </a>
      </div>
    </td>
  </tr>
`;

const buildPaginationHTML = (
  current: number,
  total: number,
  isFirst: boolean,
  isLast: boolean,
) => {
  let html = `
    <li class="page-item previous ${isFirst ? "disabled" : ""}">
      <a href="javascript:void(0);" class="page-link" data-page="${
        current - 1
      }">
        <i class="previous"></i>
      </a>
    </li>
  `;

  for (let i = 0; i < total; i++) {
    html += `
      <li class="page-item ${i === current ? "active" : ""}">
        <a href="javascript:void(0);" class="page-link" data-page="${i}">${
          i + 1
        }</a>
      </li>
    `;
  }
  html += `
    <li class="page-item next ${isLast ? "disabled" : ""}">
      <a href="javascript:void(0);" class="page-link" data-page="${
        current + 1
      }">
        <i class="next"></i>
      </a>
    </li>
  `;
  return html;
};

const bindPaginationEvents = () => {
  document.querySelectorAll(".page-link").forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      const page = parseInt(
        (e.currentTarget as HTMLElement).dataset.page || "0",
      );
      getFeaturedMembers({ page });
    });
  });
};

const bindDeleteEvents = () => {
  document.querySelectorAll(".delete-featured-member").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      const ref = (e.currentTarget as HTMLElement).dataset.memberRef;
      if (!ref) return;

      const result = await window.Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to undo this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
      });

      if (!result.isConfirmed) return;

      const el = (e.target as HTMLElement)?.closest(".col-md-6, tr")!;

      const parent = el.parentElement!;
      const index = Array.from(parent.children).indexOf(el);

      el.remove();
      const success = await handleDelete(ref);

      if (!success) {
        index >= parent.children.length
          ? parent.appendChild(el)
          : parent.insertBefore(el, parent.children[index]);

        showToast("Failed to delete member. It has been restored.", true);
      }
    });
  });
};

const bindEditEvents = () => {
  document.querySelectorAll(".edit-featured-member").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      const ref = (e.currentTarget as HTMLElement).dataset.memberRef;
      if (!ref) return;

      // Load modal
      const modalEl = $("#kt_modal_new_target");
      const form = $("form#kt_modal_new_featured_form");

      // Add hidden input for memberRef (if not exists)
      if (!form.find("input[name='memberRef']").length) {
        form.append(`<input type="hidden" name="memberRef" />`);
      }

      // Fetch the single featured member
      const member = await getFeaturedMember(ref);
      if (!member) {
        showToast("Failed to load member details", true);
        return;
      }

      $("#kt_featured_member_image_input").css(
        "background-image",
        `url('${baseURL + member.avatar}')`,
      );

      // Fill the form
      const select = form.find("select[name='title']");
      select.val(member.title);
      window.$(select).trigger("change");
      form.find("input[name='first_name']").val(member.first_name);
      form.find("input[name='middle_name']").val(member.middle_name);
      form.find("input[name='last_name']").val(member.last_name);
      form.find("input[name='position']").val(member.position);
      form.find("input[name='speciality']").val(member.speciality);
      form.find("textarea[name='job_description']").val(member.job_description);
      form.find("input[name='memberRef']").val(member.memberRef);

      const formEl = document.getElementById("kt_modal_new_featured_form");
      setFormToEditMode(formEl);

      // Change modal title + submit button text
      modalEl.find(".modal-heading").text("Edit Featured Member");
      modalEl.find("button[type='submit']").text("Update Member");

      // Show modal
      if (modalEl.length > 0) {
        const modal = new window.bootstrap.Modal(modalEl);
        modal.show();
      }
    });
  });
};

document
  .getElementById("kt_modal_new_target")
  ?.addEventListener("hidden.bs.modal", function (e) {
    const target = e.currentTarget!;

    const form = $("form#kt_modal_new_featured_form");
    form.trigger("reset");
    setFormToCreateMode(form[0]);

    const requiredFields: (keyof FeaturedMemberFormData)[] = [
      "avatar",
      "title",
      "first_name",
      "last_name",
      "position",
      "job_description",
    ];

    // Clear previous errors
    clearFeaturedMemberErrors(form[0] as HTMLFormElement, requiredFields);

    const select = form.find("select[name='title']");
    select.val("");
    window.$(select).trigger("change");
    $("#kt_featured_member_image_input").css(
      "background-image",
      `url('${baseURL}orc/assets/media/svg/avatars/blank-dark.svg')`,
    );

    // Remove edit state
    form.find("input[name='memberRef']").remove();
    $(target).find(".modal-heading").text("Add New Featured Member");
    form.find("button[type='submit']").text("Submit");
  });

const renderFeaturedMembers = (data: PaginatedResponse<IFeaturedMember>) => {
  const cardPane = document.querySelector(
    "#kt_featured_members_card_pane .row",
  );
  const tablePane = document.querySelector(
    "#kt_featured_members_table_pane tbody",
  );
  const cardPagination = document.querySelector(
    "#kt_featured_members_card_pane ul.pagination",
  );
  const tablePagination = document.querySelector(
    "#kt_featured_members_table_paginate ul.pagination",
  );
  const cardDetails = document.querySelector(".card-pane-pagination-details");

  const h3 = document.querySelector(".featured-members-count");
  if (h3) {
    h3.innerHTML = `Members (${data.totalElements})`;
  }

  if (cardDetails)
    cardDetails.innerHTML = `Showing ${data.currentPage * data.size + 1} to ${
      data.size * (data.currentPage + 1)
    } of ${data.totalPages} entries`;

  if (cardPane) cardPane.innerHTML = data.result.map(buildCardHTML).join("");
  if (tablePane) tablePane.innerHTML = data.result.map(buildTableHTML).join("");

  if (cardPagination)
    cardPagination.innerHTML = buildPaginationHTML(
      data.currentPage,
      data.totalPages,
      data.first,
      data.last,
    );
  if (tablePagination)
    tablePagination.innerHTML = buildPaginationHTML(
      data.currentPage,
      data.totalPages,
      data.first,
      data.last,
    );

  bindPaginationEvents();
  bindDeleteEvents();
  bindEditEvents();
};

if (document.querySelector("#kt_daterangepicker_3")) {
  console.log("....", moment().format("YYYY"));

  const daterangepicker = document.querySelector("#kt_daterangepicker_3");
  if (daterangepicker) {
    (window.$(daterangepicker) as any).daterangepicker({
      singleDatePicker: true,
      showDropdowns: true,
      minYear: 1901,
      maxYear: parseInt(moment().format("YYYY"), 12),
      locale: {
        format: "MM/DD/YYYY",
      },
    });
  }
}

if (document.querySelector("#kt_datepicker_publish")) {
  const publishInput = document.querySelector("#kt_datepicker_publish") as any;
  if (window.flatpickr) {
    window.flatpickr(publishInput, {
      enableTime: true,
      dateFormat: "Y-m-d H:i",
      defaultDate: new Date(),
      placeholder: "Select Date and Time",
    });
  }
}

if (document.querySelector("#kt_tagify_2")) {
  const input = document.querySelector("#kt_tagify_2");
  const tagify = new window.Tagify(input, {
    delimiters: ",",
    pattern: /^[a-zA-Z0-9_]+$/,
    maxTags: 10,
    placeholder: "Enter tags...",
    dropdown: {
      enabled: true,
      closeOnSelect: false,
      position: "bottom",
    },
  });
}

/**
 * Custom Upload Adapter for CKEditor
 */
class MyUploadAdapter {
  loader: any;
  xhr: XMLHttpRequest | null;

  constructor(loader: any) {
    // The file loader instance to use during the upload.
    this.loader = loader;
    this.xhr = null;
  }

  // Starts the upload process.
  upload() {
    return this.loader.file.then(
      (file: File) =>
        new Promise((resolve, reject) => {
          this._initRequest();
          this._initListeners(resolve, reject, file);
          this._sendRequest(file);
        })
    );
  }

  // Aborts the upload process.
  abort() {
    if (this.xhr) {
      this.xhr.abort();
    }
  }

  // Initializes the XMLHttpRequest object
  _initRequest() {
    const xhr = (this.xhr = new XMLHttpRequest());
    // TODO: Update the URL to your actual endpoint
    xhr.open("POST", `${baseURL}api/upload-image`, true);
    xhr.responseType = "json";
  }

  // Initializes XMLHttpRequest listeners.
  _initListeners(resolve: (value: any) => void, reject: (reason?: any) => void, file: File) {
    const xhr = this.xhr;
    const loader = this.loader;
    const genericErrorText = `Couldn't upload file: ${file.name}.`;

    xhr!.addEventListener("error", () => reject(genericErrorText));
    xhr!.addEventListener("abort", () => reject());
    xhr!.addEventListener("load", () => {
      const response = xhr!.response;

      if (!response || response.error) {
        return reject(
          response && response.error ? response.error.message : genericErrorText
        );
      }

      // If the upload is successful, resolve the upload promise with an object containing
      // at least the "default" URL, pointing to the image on the server.
      // This URL will be used to display the image in the content.
      resolve({
        default: response.url,
      });
    });

    if (xhr!.upload) {
      xhr!.upload.addEventListener("progress", (evt) => {
        if (evt.lengthComputable) {
          loader.uploadTotal = evt.total;
          loader.uploaded = evt.loaded;
        }
      });
    }
  }

  // Prepares the data and sends the request.
  _sendRequest(file: File) {
    const data = new FormData();
    data.append("upload", file);
    this.xhr!.send(data);
  }
}

function MyCustomUploadAdapterPlugin(editor: any) {
  editor.plugins.get("FileRepository").createUploadAdapter = (loader: any) => {
    return new MyUploadAdapter(loader);
  };
}

if (document.querySelector("#kt_docs_ckeditor_document")) {
  window.DecoupledEditor.create(
    document.querySelector("#kt_docs_ckeditor_document"),
    { 
      placeholder: "Start writing your post...",
      extraPlugins: [MyCustomUploadAdapterPlugin],
    },
  )
    .then((editor: any) => {
      console.log({ editor });

      const toolbarContainer = document.querySelector(
        "#kt_docs_ckeditor_document_toolbar",
      );

      toolbarContainer?.appendChild(editor.ui.view.toolbar.element);
    })
    .catch((error: any) => {
      console.error(error);
    });
}

(function () {
  redirectIfNotAuthenticated();
  getUserProfile();

  const searchBox = document.querySelector(
    "#kt_featured_members_search",
  ) as HTMLInputElement;
  if (searchBox) {
    searchBox.addEventListener("keyup", () => {
      getFeaturedMembers({ search: searchBox.value });
    });
  }

  document
    .querySelectorAll(".sign-out")
    .forEach((a) => a.addEventListener("click", handleSignout));
  const formEl = document.getElementById("kt_modal_new_featured_form");
  setFormToCreateMode(formEl);

  const blogPostForm = document.getElementById("blog-post-form");
  if (blogPostForm) {
    const pathname = window.location.pathname;
    const urlParams = new URLSearchParams(window.location.search);
    const postRef = urlParams.get("ref");

    if (pathname.includes("edit-blog-post") && postRef) {
      // Edit mode
      blogPostForm.addEventListener("submit", (e) => handleUpdateBlogPostForm(e, postRef));
      fetchCategories().then(() => {
        fetchBlogPostByRef(postRef);
      });
    } else {
      // Create mode
      blogPostForm.addEventListener("submit", handleBlogPostForm);
      bindSlugGenerator();
      fetchCategories();
    }
  }

  const pathname = window.location.pathname;
  if (pathname.includes("list-blog-posts")) {
    initBlogList();
  }

  updateSidebarActiveState();

  const addCategoryForm = document.getElementById("kt_modal_add_category_form");
  if (addCategoryForm) {
    addCategoryForm.addEventListener("submit", handleCategoryForm);
  }

  subscribe((change) => {
    const user = change.state.user;
    const featuredMembers = change.state.featuredMembers;

    const pathname = window.location.pathname;
    if (pathname.includes("featured-members") && user?.role !== "admin") {
      window.location.href = `${baseURL}admin/`;
    }

    if (change.key === "user" && user) {
      if (user.role === "admin") getFeaturedMembers({});
      updateUserUI(user);
      updateUserLinksUI(user);
    }
    if (change.key === "featuredMembers" && featuredMembers)
      renderFeaturedMembers(featuredMembers);
    
    if (change.key === "blogPosts" && change.state.blogPosts)
      renderBlogPosts(change.state.blogPosts);
  });
})();
