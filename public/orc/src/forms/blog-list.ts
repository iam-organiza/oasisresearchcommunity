import { deleteBlogPost, getBlogPosts, handleDeleteBlogPost } from "../services/blog";
import { IBlogPost, PaginatedResponse, subscribe } from "../store";
import { prettifyDate, showToast } from "../utils/helper.utils";

const buildBlogPostCardHTML = (post: IBlogPost) => `
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-body d-flex flex-column p-9 position-relative">
        <a href="javascript:void(0);" data-post-ref="${
          post.postRef
        }" class="delete-blog-post text-gray-800 text-hover-danger position-absolute" style="top: 10px; right: 10px;">
          <span class="svg-icon svg-icon-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 9C5 8.44772 5.44772 8 6 8H18C18.5523 8 19 8.44772 19 9V18C19 19.6569 17.6569 21 16 21H8C6.34315 21 5 19.6569 5 18V9Z" fill="currentColor"/>
              <path opacity="0.5" d="M5 5C5 4.44772 5.44772 4 6 4H18C18.5523 4 19 4.44772 19 5V5C19 5.55228 18.5523 6 18 6H6C5.44772 6 5 5.55228 5 5V5Z" fill="currentColor"/>
              <path opacity="0.5" d="M9 4C9 3.44772 9.44772 3 10 3H14C14.5523 3 15 3.44772 15 4V4H9V4Z" fill="currentColor"/>
            </svg>
          </span>
        </a>
        <a href="/admin/blog/edit-blog-post/?ref=${
          post.postRef
        }" class="text-gray-800 text-hover-primary position-absolute" style="top: 10px; left: 10px;">
          <span class="svg-icon svg-icon-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.2869 3.15178L14.3601 4.07866L5.83882 12.5999L5.83881 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021L2.05445 20.6042C1.92743 20.9852 2.0266 21.4053 2.31063 21.6894C2.59466 21.9734 3.01478 22.0726 3.39584 21.9456L4.19792 21.6782L7.47918 20.5844L7.47919 20.5844C8.25353 20.3263 8.6407 20.1973 9.00498 20.0237C9.43469 19.8189 9.84082 19.5679 10.2162 19.2751C10.5344 19.0269 10.8229 18.7383 11.4001 18.1612L11.4001 18.1612L19.9213 9.63993L20.8482 8.71306C22.3839 7.17735 22.3839 4.68748 20.8482 3.15178C19.3125 1.61607 16.8226 1.61607 15.2869 3.15178Z" stroke="currentColor" stroke-width="1.5"/>
                <path opacity="0.5" d="M14.36 4.07812C14.36 4.07812 14.4759 6.04774 16.2138 7.78564C17.9517 9.52354 19.9213 9.6394 19.9213 9.6394M4.19789 21.6777L2.32178 19.8015" stroke="currentColor" stroke-width="1.5"/>
              </svg>
          </span>
        </a>
        <div class="mb-5 text-center mt-5">
           <img src="/${post.featured_image || 'assets/media/svg/files/blank-image.svg'}" 
                class="rounded w-100 h-150px" style="object-fit: cover;" alt="image">
        </div>
        <div class="mb-2">
          <div class="d-flex align-items-center mb-2">
            <span class="badge badge-light-primary fw-bold me-auto">${post.category}</span>
            <span class="badge badge-light-${post.status === 'Published' ? 'success' : (post.status === 'Scheduled' ? 'warning' : 'info')} fw-bold">${post.status}</span>
          </div>
          <a href="/admin/blog/edit-blog-post/?ref=${post.postRef}" class="fs-4 text-gray-800 text-hover-primary fw-bold mb-0">${post.title}</a>
        </div>
        <div class="fw-semibold text-gray-400 mb-6">${post.excerpt.substring(0, 100)}${post.excerpt.length > 100 ? '...' : ''}</div>
        <div class="d-flex flex-stack mt-auto">
          <span class="text-gray-400 fw-bold fs-7">By ${post.author}</span>
          <span class="text-gray-400 fw-bold fs-7">${prettifyDate(post.publish_date || post.created_at)}</span>
        </div>
      </div>
    </div>
  </div>
`;

const buildBlogPostTableHTML = (post: IBlogPost, index: number) => `
  <tr class="${index % 2 === 0 ? "odd" : "even"}">
    <td>
      <div class="symbol symbol-50px">
        <img alt="Thumbnail" src="/${post.featured_image || 'assets/media/svg/files/blank-image.svg'}" style="object-fit: cover;">
      </div>
    </td>
    <td>
      <a href="/admin/blog/edit-blog-post/?ref=${post.postRef}" class="text-gray-800 text-hover-primary fw-bold">${post.title}</a>
    </td>
    <td><span class="badge badge-light-primary">${post.category}</span></td>
    <td>${post.author}</td>
    <td class="text-end">${prettifyDate(post.publish_date || post.created_at)}</td>
    <td class="text-end">
        <span class="badge badge-light-${post.status === 'Published' ? 'success' : (post.status === 'Scheduled' ? 'warning' : 'info')}">${post.status}</span>
    </td>
    <td class="text-end">
      <div class="d-flex justify-content-end gap-2">
        <a href="/admin/blog/edit-blog-post/?ref=${post.postRef}" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1">
          <span class="svg-icon svg-icon-3">
             <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15.2869 3.15178L14.3601 4.07866L5.83882 12.5999L5.83881 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021L2.05445 20.6042C1.92743 20.9852 2.0266 21.4053 2.31063 21.6894C2.59466 21.9734 3.01478 22.0726 3.39584 21.9456L4.19792 21.6782L7.47918 20.5844L7.47919 20.5844C8.25353 20.3263 8.6407 20.1973 9.00498 20.0237C9.43469 19.8189 9.84082 19.5679 10.2162 19.2751C10.5344 19.0269 10.8229 18.7383 11.4001 18.1612L11.4001 18.1612L19.9213 9.63993L20.8482 8.71306C22.3839 7.17735 22.3839 4.68748 20.8482 3.15178C19.3125 1.61607 16.8226 1.61607 15.2869 3.15178Z" stroke="currentColor" stroke-width="1.5"/>
             </svg>
          </span>
        </a>
        <a href="javascript:void(0);" data-post-ref="${post.postRef}" class="delete-blog-post btn btn-icon btn-bg-light btn-active-color-danger btn-sm">
          <span class="svg-icon svg-icon-3">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M5 9C5 8.44772 5.44772 8 6 8H18C18.5523 8 19 8.44772 19 9V18C19 19.6569 17.6569 21 16 21H8C6.34315 21 5 19.6569 5 18V9Z" fill="currentColor"/>
              <path opacity="0.5" d="M5 5C5 4.44772 5.44772 4 6 4H18C18.5523 4 19 4.44772 19 5V5C19 5.55228 18.5523 6 18 6H6C5.44772 6 5 5.55228 5 5V5Z" fill="currentColor"/>
              <path opacity="0.5" d="M9 4C9 3.44772 9.44772 3 10 3H14C14.5523 3 15 3.44772 15 4V4H9V4Z" fill="currentColor"/>
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
  document.querySelectorAll("#kt_blog_posts_card_pane .page-link, #kt_blog_posts_table_pane .page-link").forEach((el) => {
    el.addEventListener("click", (e) => {
      e.preventDefault();
      const page = parseInt(
        (e.currentTarget as HTMLElement).dataset.page || "0",
      );
      getBlogPosts({ page });
    });
  });
};

const bindDeleteEvents = () => {
  document.querySelectorAll(".delete-blog-post").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      const ref = (e.currentTarget as HTMLElement).dataset.postRef;
      if (!ref) return;

      const result = await (window as any).Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to undo this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
      });

      if (!result.isConfirmed) return;

      const success = await handleDeleteBlogPost(ref);

      if (success) {
        (window as any).showToast("Blog post deleted successfully");
      } else {
        (window as any).showToast("Failed to delete blog post.", true);
      }
    });
  });
};

export const renderBlogPosts = (data: PaginatedResponse<IBlogPost>) => {
  const cardPane = document.querySelector("#kt_blog_posts_card_pane .row.g-6.g-xl-9");
  const tableBody = document.querySelector("#kt_blog_posts_table tbody");
  
  const cardPagination = document.querySelector("#kt_blog_posts_card_pane ul.pagination");
  const tablePagination = document.querySelector("#kt_blog_posts_table_pane ul.pagination");
  
  const countEl = document.querySelector(".blog-posts-count");

  if (countEl) {
    countEl.innerHTML = `Blog Posts (${data.totalElements})`;
  }

  if (cardPane) {
      cardPane.innerHTML = data.result.map(buildBlogPostCardHTML).join("");
  }

  if (tableBody) {
      tableBody.innerHTML = data.result.map(buildBlogPostTableHTML).join("");
  }

  if (cardPagination) {
      cardPagination.innerHTML = buildPaginationHTML(data.currentPage, data.totalPages, data.first, data.last);
  }

  if (tablePagination) {
      tablePagination.innerHTML = buildPaginationHTML(data.currentPage, data.totalPages, data.first, data.last);
  }

  bindPaginationEvents();
  bindDeleteEvents();
};

export const initBlogList = () => {
    const searchBox = document.getElementById("kt_blog_posts_search") as HTMLInputElement;
    if (searchBox) {
        searchBox.addEventListener("keyup", () => {
            getBlogPosts({ search: searchBox.value });
        });
    }
    
    getBlogPosts({});
};
