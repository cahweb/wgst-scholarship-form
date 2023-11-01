// Run this when the DOM is fully loaded
window.onload = function() {
    // Add the EventListeners to the file inputs, so we can update
    // their text with selected filenames.
    const fileInputs = document.querySelectorAll('input[type=file]')

    for (const input of fileInputs) {
        input.addEventListener('change', event => { updateFileInputLabel(event) })
    }
}

/**
 * Updates the label element of the file input
 *
 * Changes the displayed text of the input to reflect the newly-chosen
 * file the user selected to upload.
 *
 * @param {Event} event The onChange event from the file input
 *
 * @returns {void}
 */
function updateFileInputLabel(event) {
    // Get the name of the file
    const fileName = getUploadedFileName(event.target.value)
    const labelSpan = event.target.nextElementSibling
    /* 
     * We can't update the `::after` pseudo-selector directly, but in the CSS
     * we can set the `content` attribute of that element to match an
     * arbitrary `data-` attribute on the element in question, and changing
     * that will successfully update the text. (Bless you, StackOverflow.)
     */
    labelSpan.setAttribute('data-after', fileName ? fileName : "Choose file...")
}


/**
 * Gets the name of the uploaded file
 *
 * Strips and returns the uploaded file name, filtered from the full
 * (fake) filepath provided to JavaScript by the browser.
 *
 * @param {String} fakePath The uploaded filename value in JavaScript
 *
 * @returns {String}
 */
function getUploadedFileName(fakePath) {
    // This will handle most modern browsers
    if (fakePath.substring(0, 12) == "C:\\fakepath\\") {
        return fakePath.substring(12)
    }

    /* Catching old versions and edge cases */
    const separators = [
        '/',  // Unix and MacOS
        '\\', // Windows
    ]

    // Loop through and find which applies, then return the filename
    for  (const separator of separators) {
        const lastSlash = fakePath.lastIndexOf(separator)
        if (lastSlash >= 0) {
            return fakePath.substring(lastSlash + 1)
        }
    }
}