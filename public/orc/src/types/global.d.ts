export {};

declare global {
  interface Window {
    KTImageInput: {
      /**
       * Constructor/init function
       * @param element Target DOM element or selector
       * @param options Optional settings
       */
      (element: any, options?: any): void;

      /**
       * Get existing instance by element
       * @param element Target element or ID
       */
      getInstance(element: any): any;

      /**
       * Create image input instances for matched elements
       * @param selector Optional CSS selector (default: `[data-kt-image-input]`)
       */
      createInstances(selector?: string): void;

      /**
       * Initializes all image inputs
       */
      init(): void;
    };
    $: JQueryStatic & { daterangepicker: any };
    KTBlockUI: any;
    Swal: any;
    bootstrap: any;
    DecoupledEditor: any;
    tns: any;
    Tagify: any;
  }
}

declare global {
  interface JQuery {
    daterangepicker(options?: any): JQuery;
  }
}

declare module "jquery" {
  interface JQueryStatic {
    blockUI(options?: any): void;
    unblockUI(options?: any): void;
    growlUI?(
      title?: string,
      message?: string,
      timeout?: number,
      onClose?: () => void,
    ): void;
  }

  interface JQuery {
    block(options?: any): void;
    unblock(options?: any): void;
  }
}
