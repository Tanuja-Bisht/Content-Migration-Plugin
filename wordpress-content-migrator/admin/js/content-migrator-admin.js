/**
 * Admin JavaScript for Content Migrator
 */
;(($) => {
  $(document).ready(() => {
    // File input validation
    $("#excel_file").on("change", function () {
      const file = this.files[0]
      if (file) {
        const fileExt = file.name.split(".").pop().toLowerCase()
        if (fileExt !== "xlsx" && fileExt !== "csv") {
          alert("Please upload an Excel (.xlsx) or CSV (.csv) file.")
          this.value = ""
        }
      }
    })
  })
})(jQuery)
