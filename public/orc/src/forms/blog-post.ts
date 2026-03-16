import {instance} from "../config/axios.config";
import {showToast} from "../utils/helper.utils";

export interface BlogPostFormData {
  title: string;
  slug?: string;
  excerpt?: string;
  content: string;
  meta_title?: string;
  canonical_url?: string;
  meta_description?: string;
  og_title?: string;
  og_description?: string;
  reading_time?: number;
  status: string;
  author: string;
  category: string;
  tags?: string;
  visibility: string;
  allow_comments: boolean;
  is_featured: boolean;
  is_editors_choice: boolean;
  is_popular: boolean;
  content_type: string;
  publish_date?: string;
  featured_image?: File | null;
  og_image?: File | null;
}

/**
 * Helper to display validation errors on specific fields
 */
export const showFormError = (form: HTMLFormElement, name: string, message: string) => {
  const input = form.querySelector(`[name="${name}"]`) as HTMLElement;
  if (!input) return;
  
  // Create or update error text
  let errorEl = input.parentElement?.querySelector(".invalid-feedback");
  if (!errorEl) {
    errorEl = document.createElement("div");
    errorEl.className = "invalid-feedback d-block mt-2";
    input.parentElement?.appendChild(errorEl);
  }
  errorEl.textContent = message;
  input.classList.add("is-invalid");
};

/**
 * Clears all validation errors from the form
 */
export const clearFormErrors = (form: HTMLFormElement) => {
  form.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
  form.querySelectorAll(".invalid-feedback").forEach(el => el.remove());

  const editorDocument = document.querySelector(".ck-editor__editable") || document.querySelector("#kt_docs_ckeditor_document");
  if (editorDocument) {
    editorDocument.classList.remove("border", "border-danger");
  }

  const tagifyContainer = document.querySelector("tags.tagify") as HTMLElement;
  if (tagifyContainer) {
    tagifyContainer.classList.remove("border", "border-danger");
  }
};

/**
 * Validates the blog post form data
 */
const validateBlogPostForm = (form: HTMLFormElement, data: BlogPostFormData): boolean => {
  clearFormErrors(form);
  let isValid = true;

  if (!data.title.trim()) {
    showFormError(form, "title", "Title is required");
    isValid = false;
  }

  const cleanContent = data.content.replace(/<[^>]*>?/gm, '').replace(/&nbsp;/g, '').trim();
  const hasMedia = data.content.includes('<img') || data.content.includes('<iframe') || data.content.includes('<video') || data.content.includes('<figure');

  if (!cleanContent && !hasMedia) {
     showToast("Post content cannot be empty", true);
     const editorDocument = document.querySelector(".ck-editor__editable") || document.querySelector("#kt_docs_ckeditor_document");
     if (editorDocument) {
         editorDocument.classList.add("border", "border-danger");
     }
     isValid = false;
  }

  if (!data.status) {
    showFormError(form, "status", "Status is required");
    isValid = false;
  }

  if (!data.author) {
    showFormError(form, "author", "Author is required");
    isValid = false;
  }

  if (!data.category) {
    showFormError(form, "category", "Category is required");
    isValid = false;
  }

  // Tags validation - Tagify returns a stringified array format e.g. '[{"value":"holiday"}]' or an empty string / empty array string
  if (!data.tags || data.tags.trim() === "" || data.tags.trim() === "[]") {
     // No standard input for Tagify to attach invalid-feedback cleanly, so we show a global toast and red border 
     showToast("Please add at least one tag", true);
     const tagifyContainer = document.querySelector("tags.tagify") as HTMLElement;
     if (tagifyContainer) {
         tagifyContainer.classList.add("border", "border-danger");
     }
     isValid = false;
  }

  return isValid;
};

/**
 * Handles the submission of the Create Blog Post form
 */
export const handleBlogPostForm = async (e: Event) => {
  e.preventDefault();
  const form = e.target as HTMLFormElement;
  const submitBtn = form.blog_post_submit_btn as HTMLButtonElement | null;
  
  // Set loading state
  if (submitBtn) {
     submitBtn.setAttribute("data-kt-indicator", "on");
     submitBtn.disabled = true;
  }
  
  try {
    const formData = new FormData(form);
    
    // Extract CKEditor content properly since it isn't an input field
    const editorElement = document.querySelector("#kt_docs_ckeditor_document") as any;
    const content = editorElement?.ckeditorInstance?.getData() || editorElement?.innerHTML || "";

    // Build the data object (for validation purposes)
    const payload: BlogPostFormData = {
      title: formData.get("title") as string,
      slug: (formData.get("slug") as string) || "",
      excerpt: (formData.get("excerpt") as string) || "",
      content: content,
      meta_title: (formData.get("meta_title") as string) || "",
      canonical_url: (formData.get("canonical_url") as string) || "",
      meta_description: (formData.get("meta_description") as string) || "",
      og_title: (formData.get("og_title") as string) || "",
      og_description: (formData.get("og_description") as string) || "",
      reading_time: parseInt((formData.get("reading_time") as string) || "0", 10),
      status: formData.get("status") as string,
      author: formData.get("author") as string,
      category: formData.get("category") as string,
      tags: formData.get("tags") as string, // Tagify input
      visibility: formData.get("visibility") as string,
      allow_comments: formData.get("allow_comments") === "1",
      is_featured: formData.get("is_featured") === "1",
      is_editors_choice: formData.get("is_editors_choice") === "1",
      is_popular: formData.get("is_popular") === "1",
      content_type: formData.get("content_type") as string,
      publish_date: formData.get("publish_date") as string,
      featured_image: formData.get("featured_image") as File,
      og_image: formData.get("og_image") as File,
    };

    // Because 'slug' and 'content' aren't standard form inputs (slug is disabled/readonly without 'name' submission sometimes, and content is CKeditor),
    // we explicitly append them into the formData so the backend receives them correctly.
    formData.set("content", payload.content);
    formData.set("slug", payload.slug || "");

    // Validate
    if (!validateBlogPostForm(form, payload)) {
       showToast("Please correct the errors before submitting", true);
       return;
    }

    console.log("Valid blog post data to submit:", payload);
    
    // Note: Since we are uploading files, we must send the raw FormData object.
    // Our configured Axios instance defaults to application/json, so we explicitly override it.
    const response = await instance.post("api/blog-posts", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    
    if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to create blog post");
    }
    
    showToast("Blog post created successfully!");
    form.reset();
    
    // Clear editor content
    if (editorElement?.ckeditorInstance) {
       editorElement.ckeditorInstance.setData("");
    } else {
       editorElement.innerHTML = "";
    }
    
  } catch (error: any) {
    console.error("Error creating blog post:", error);
    showToast(error.message || "An unexpected error occurred", true);
  } finally {
    // Remove loading state
    if (submitBtn) {
       submitBtn.removeAttribute("data-kt-indicator");
       submitBtn.disabled = false;
    }
  }
};

/**
 * Attaches a listener to the title input to auto-generate the slug.
 */
export const bindSlugGenerator = () => {
  const titleInput = document.getElementById("title") as HTMLInputElement | null;
  const slugInput = document.getElementById("slug") as HTMLInputElement | null;

  if (titleInput && slugInput) {
    const generateSlug = () => {
      const title = titleInput.value;
      const slug = title
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, "") // Remove non-word characters
        .replace(/[\s_-]+/g, "-") // Replace spaces and underscores with hyphens
        .replace(/^-+|-+$/g, ""); // Remove leading/trailing hyphens
      
      slugInput.value = slug;
    };

    titleInput.addEventListener("input", generateSlug);
    titleInput.addEventListener("change", generateSlug);
  }
};

/**
 * Handles the submission of the Add Category modal form
 */
export const handleCategoryForm = async (e: Event) => {
  e.preventDefault();
  const form = e.target as HTMLFormElement;
  const submitBtn = form.querySelector('[type="submit"]') as HTMLButtonElement | null;
  const cancelBtn = form.querySelector('[type="reset"]') as HTMLButtonElement | null;

  const categoryNameInput = form.querySelector('[name="category_name"]') as HTMLInputElement;
  const categoryDescInput = form.querySelector('[name="category_description"]') as HTMLTextAreaElement;

  if (!categoryNameInput.value.trim()) {
    showToast("Category name is required", true);
    return;
  }

  // Set loading state
  if (submitBtn) {
    submitBtn.setAttribute("data-kt-indicator", "on");
    submitBtn.disabled = true;
  }

  try {
    const payload = {
      name: categoryNameInput.value.trim(),
      description: categoryDescInput?.value.trim() || ""
    };

    console.log('payload', payload);

    const response = await instance.post("api/blog-categories", payload);
    
    if (!response.data || !response.data.success) {
      throw new Error(response.data?.message || "Failed to create category");
    }

    const newCategory = response.data.data.category; // Expected structure from API

    // Dynamically add the new option to the dropdown and securely select it
    const categorySelect = document.getElementById("category") as HTMLSelectElement | null;
    if (categorySelect && newCategory && newCategory.name) {
      const capitalizedCategoryName = newCategory.name.charAt(0).toUpperCase() + newCategory.name.slice(1);
      const newOption = new Option(capitalizedCategoryName, newCategory.name, true, true);
      categorySelect.add(newOption);
      
      // If using Select2, trigger a visual update
      if ($(categorySelect).data('select2')) {
        $(categorySelect).trigger('change');
      }
    }
    
    showToast("Category added successfully!");
    form.reset();
    
    // Close the bootstrap modal using the cancel button
    if (cancelBtn) {
        cancelBtn.click();
    }

  } catch (error: any) {
    console.error("Error creating category:", error);
    showToast(error.message || "An unexpected error occurred", true);
  } finally {
    // Remove loading state
    if (submitBtn) {
      submitBtn.removeAttribute("data-kt-indicator");
      submitBtn.disabled = false;
    }
  }
};

/**
 * Fetches categories from the API and populates the dropdown.
 */
export const fetchCategories = async () => {
  try {
    const categorySelect = document.getElementById("category") as HTMLSelectElement | null;
    if (!categorySelect) return;

    const response = await instance.get("api/blog-categories");
    
    if (response.data && response.data.success) {
      const categories = response.data.data.categories;
      
      // Clear existing hardcoded options
      categorySelect.innerHTML = "";
      
      // If there are no categories, we add a placeholder
      if (!categories || categories.length === 0) {
        categorySelect.add(new Option("No categories found", ""));
      } else {
        categories.forEach((cat: any) => {
          const capitalizedCategoryName = cat.name.charAt(0).toUpperCase() + cat.name.slice(1);
          const option = new Option(capitalizedCategoryName, cat.name);
          categorySelect.add(option);
        });
      }

      // Automatically sync UI updates if Select2 is initialized
      if ($(categorySelect).data('select2')) {
        $(categorySelect).trigger('change');
      }
    }
  } catch (error) {
    console.error("Failed to fetch categories:", error);
  }
};
