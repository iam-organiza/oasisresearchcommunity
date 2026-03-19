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
  let input = form.querySelector(`[name="${name}"]`) as HTMLElement;
  
  // Special handling for KTImageInput fields which might be identified by parent ID
  if (!input) {
    if (name === "featured_image") {
       input = document.querySelector("#kt_blog_post_featured_image_input") as HTMLElement;
    } else if (name === "og_image") {
       input = document.querySelector("#kt_blog_post_og_image_input") as HTMLElement;
    }
  }

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
export const validateBlogPostForm = (form: HTMLFormElement, data: BlogPostFormData, isUpdate: boolean = false): boolean => {
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

  const MAX_SIZE = 5 * 1024 * 1024; // 5MB

   if (data.featured_image && data.featured_image.size > MAX_SIZE) {
       showFormError(form, "featured_image", "Featured image size must not exceed 5MB");
       isValid = false;
   } else if (!isUpdate && !data.featured_image) {
       showFormError(form, "featured_image", "Featured image is required");
       isValid = false;
   }

  if (data.og_image && data.og_image.size > MAX_SIZE) {
      showFormError(form, "og_image", "Open Graph image size must not exceed 5MB");
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

    var ogImageInput = document.querySelector("#kt_blog_post_og_image_input");
    var ogImageInputInstance = window.KTImageInput.getInstance(ogImageInput);
    
    var featuredImageInput = document.querySelector("#kt_blog_post_featured_image_input");
    var featuredImageInputInstance = window.KTImageInput.getInstance(featuredImageInput);

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
      featured_image: featuredImageInputInstance.inputElement.files.length ? featuredImageInputInstance.inputElement.files[0] as File : null,
      og_image: ogImageInputInstance.inputElement.files.length ? ogImageInputInstance.inputElement.files[0] as File : null,
    };

    // Because 'slug' and 'content' aren't standard form inputs (slug is disabled/readonly without 'name' submission sometimes, and content is CKeditor),
    // we explicitly append them into the formData so the backend receives them correctly.
    // Explicitly set all values from payload to formData to ensure everything is sent
    formData.set("title", payload.title);
    formData.set("slug", payload.slug || "");
    formData.set("excerpt", payload.excerpt || "");
    formData.set("content", payload.content);
    formData.set("meta_title", payload.meta_title || "");
    formData.set("canonical_url", payload.canonical_url || "");
    formData.set("meta_description", payload.meta_description || "");
    formData.set("og_title", payload.og_title || "");
    formData.set("og_description", payload.og_description || "");
    formData.set("reading_time", (payload.reading_time || 0).toString());
    formData.set("status", payload.status);
    formData.set("author", payload.author);
    formData.set("category", payload.category);
    formData.set("tags", payload.tags || "");
    formData.set("visibility", payload.visibility);
    formData.set("content_type", payload.content_type);
    formData.set("publish_date", payload.publish_date || "");

    // Explicitly set boolean fields as "1" or "0"
    formData.set("allow_comments", payload.allow_comments ? "1" : "0");
    formData.set("is_featured", payload.is_featured ? "1" : "0");
    formData.set("is_editors_choice", payload.is_editors_choice ? "1" : "0");
    formData.set("is_popular", payload.is_popular ? "1" : "0");

    // Add images if they were selected
    if (payload.featured_image) {
      formData.set("featured_image", payload.featured_image);
    }
    if (payload.og_image) {
      formData.set("og_image", payload.og_image);
    }

    // Validate
    if (!validateBlogPostForm(form, payload, false)) {
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
 * Handles the submission of the Update Blog Post form
 */
export const handleUpdateBlogPostForm = async (e: Event, postRef: string) => {
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
    
    // Extract CKEditor content properly
    const editorElement = document.querySelector("#kt_docs_ckeditor_document") as any;
    const content = editorElement?.ckeditorInstance?.getData() || editorElement?.innerHTML || "";

    var ogImageInput = document.querySelector("#kt_blog_post_og_image_input");
    var ogImageInputInstance = window.KTImageInput.getInstance(ogImageInput);
    
    var featuredImageInput = document.querySelector("#kt_blog_post_featured_image_input");
    var featuredImageInputInstance = window.KTImageInput.getInstance(featuredImageInput);

    // Build the data object (for validation)
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
      tags: formData.get("tags") as string,
      visibility: formData.get("visibility") as string,
      allow_comments: formData.get("allow_comments") === "1",
      is_featured: formData.get("is_featured") === "1",
      is_editors_choice: formData.get("is_editors_choice") === "1",
      is_popular: formData.get("is_popular") === "1",
      content_type: formData.get("content_type") as string,
      publish_date: formData.get("publish_date") as string,
      featured_image: featuredImageInputInstance.inputElement.files.length ? featuredImageInputInstance.inputElement.files[0] as File : null,
      og_image: ogImageInputInstance.inputElement.files.length ? ogImageInputInstance.inputElement.files[0] as File : null,
    };

    // Explicitly set all values from payload to formData to ensure everything is sent
    formData.set("title", payload.title);
    formData.set("slug", payload.slug || "");
    formData.set("excerpt", payload.excerpt || "");
    formData.set("content", payload.content);
    formData.set("meta_title", payload.meta_title || "");
    formData.set("canonical_url", payload.canonical_url || "");
    formData.set("meta_description", payload.meta_description || "");
    formData.set("og_title", payload.og_title || "");
    formData.set("og_description", payload.og_description || "");
    formData.set("reading_time", (payload.reading_time || 0).toString());
    formData.set("status", payload.status);
    formData.set("author", payload.author);
    formData.set("category", payload.category);
    formData.set("tags", payload.tags || "");
    formData.set("visibility", payload.visibility);
    formData.set("content_type", payload.content_type);
    formData.set("publish_date", payload.publish_date || "");

    // Explicitly set boolean fields as "1" or "0"
    formData.set("allow_comments", payload.allow_comments ? "1" : "0");
    formData.set("is_featured", payload.is_featured ? "1" : "0");
    formData.set("is_editors_choice", payload.is_editors_choice ? "1" : "0");
    formData.set("is_popular", payload.is_popular ? "1" : "0");

    // Add images if they were selected
    if (payload.featured_image) {
      formData.set("featured_image", payload.featured_image);
    }
    if (payload.og_image) {
      formData.set("og_image", payload.og_image);
    }

    // Validate
    if (!validateBlogPostForm(form, payload, true)) {
       showToast("Please correct the errors before submitting", true);
       return;
    }

    console.log("Valid blog post data to submit:", payload);

    // Update request
    const response = await instance.put(`api/blog-posts/${postRef}`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    
    if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to update blog post");
    }
    
    showToast("Blog post updated successfully!");
  } catch (error: any) {
    console.error("Error updating blog post:", error);
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
 * Fetches a single blog post by reference and populates the form
 */
export const fetchBlogPostByRef = async (postRef: string) => {
  try {
    const response = await instance.get(`api/blog-posts/ref/${postRef}`);
    
    if (response.data && response.data.success) {
      const post = response.data.data.post;
      const form = document.getElementById("blog-post-form") as HTMLFormElement;
      if (!form) return;

      // Populate basic fields
      (form.querySelector('[name="title"]') as HTMLInputElement).value = post.title;
      (form.querySelector('[name="slug"]') as HTMLInputElement).value = post.slug;
      (form.querySelector('[name="excerpt"]') as HTMLTextAreaElement).value = post.excerpt || "";
      (form.querySelector('[name="meta_title"]') as HTMLInputElement).value = post.meta_title || "";
      (form.querySelector('[name="canonical_url"]') as HTMLInputElement).value = post.canonical_url || "";
      (form.querySelector('[name="meta_description"]') as HTMLTextAreaElement).value = post.meta_description || "";
      (form.querySelector('[name="og_title"]') as HTMLInputElement).value = post.og_title || "";
      (form.querySelector('[name="og_description"]') as HTMLTextAreaElement).value = post.og_description || "";

      // Images preview (optional, but good for UX)
      if (post.og_image) {
        var ogImageInput = document.querySelector("#kt_blog_post_og_image_input");
        var imageInput = window.KTImageInput.getInstance(ogImageInput);

        if (ogImageInput) {
            $(ogImageInput).css('background-image', `url('/${post.og_image}')`);
        }

        imageInput.on("kt.imageinput.removed", function() {
          // console.log("kt.imageinput.remove event is fired");
          console.log('removed');
        });

        imageInput.on("kt.imageinput.canceled", function(e: any) {
            // console.log("kt.imageinput.canceled event is fired");
            console.log('canceled', e);
            e.removeElement.click();
        });
      }

      (form.querySelector('[name="reading_time"]') as HTMLInputElement).value = post.reading_time || "0";
      
      // Populate dropdowns
      const setSelectValue = (name: string, value: string) => {
        const select = form.querySelector(`[name="${name}"]`) as HTMLSelectElement;
        if (select) {
          select.value = value;
          if (window.$(select).data('select2')) {
            window.$(select).trigger('change');
          }
        }
      };
      
      setSelectValue("status", post.status);
      setSelectValue("author", post.author);
      setSelectValue("category", post.category);
      setSelectValue("visibility", post.visibility);

      // Checkboxes
      (form.querySelector('[name="allow_comments"]') as HTMLInputElement).checked = post.allow_comments == 1;
      (form.querySelector('[name="is_featured"]') as HTMLInputElement).checked = post.is_featured == 1;
      (form.querySelector('[name="is_editors_choice"]') as HTMLInputElement).checked = post.is_editors_choice == 1;
      (form.querySelector('[name="is_popular"]') as HTMLInputElement).checked = post.is_popular == 1;

      // CKEditor
      const editorElement = document.querySelector("#kt_docs_ckeditor_document") as any;
      if (editorElement?.ckeditorInstance) {
        editorElement.ckeditorInstance.setData(post.content);
      } else {
        editorElement.innerHTML = post.content;
      }

      // Tagify
      const tagifyInput = document.querySelector("#post_tags") as any;
      const tagify = new window.Tagify(tagifyInput, {});
      tagify.addTags(post.tags);

      // Images preview (optional, but good for UX)
      // Populate Images previews
      const populateImageInput = (id: string, imageUrl: string | null) => {
        const inputContainer = document.querySelector(id);
        if (!inputContainer) return;
        
        const instance = window.KTImageInput.getInstance(inputContainer);
        const wrapper = inputContainer.querySelector(".image-input-wrapper") as HTMLElement;
        
        if (imageUrl) {
          if (wrapper) {
            wrapper.style.backgroundImage = `url('/${imageUrl}')`;
          }
          inputContainer.classList.remove("image-input-empty");
          inputContainer.classList.add("image-input-changed");
        }
        
        if (instance) {
          instance.on("kt.imageinput.canceled", function(e: any) {
              e.removeElement.click();
          });
        }
      };
      
      populateImageInput("#kt_blog_post_featured_image_input", post.featured_image);
      populateImageInput("#kt_blog_post_og_image_input", post.og_image);

    } else {
      showToast("Failed to fetch blog post data", true);
    }
  } catch (error) {
    console.error("Error fetching blog post:", error);
    showToast("An error occurred while fetching blog post data", true);
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
