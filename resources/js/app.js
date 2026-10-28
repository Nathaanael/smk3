import { createPopper } from "@popperjs/core";
import Alpine from "alpinejs";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import "./bootstrap";
import initCharts from "./components/chart";
import registerUi from "./ui";

window.Alpine = Alpine;
window.createPopper = createPopper;
window.flatpickr = flatpickr;

// Register Alpine.js components before initializing
Alpine.data("dropdown", () => ({
    open: false,
    toggle() {
        this.open = !this.open;
        if (this.open) this.position();
    },
    position() {
        this.$nextTick(() => {
            const button = this.$el;
            const dropdown = this.$refs.dropdown;
            const rect = button.getBoundingClientRect();

            // Apply initial styles to ensure measurement is possible
            dropdown.style.position = "fixed";
            dropdown.style.zIndex = "999";

            const isRtl = document.documentElement.getAttribute("dir") === "rtl";
            if (isRtl) {
                dropdown.style.left = `${rect.left}px`;
                dropdown.style.right = "auto";
            } else {
                dropdown.style.right = `${window.innerWidth - rect.right - 13}px`;
                dropdown.style.left = "auto";
            }

            const dropdownHeight = Math.max(dropdown.offsetHeight, 0);
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                dropdown.style.top = `${rect.top - dropdownHeight}px`;
            } else {
                dropdown.style.top = `${rect.bottom}px`;
            }
        });
    },
    init() {
        this.$watch("open", (value) => {
            if (value) this.position();
        });
    },
}));

registerUi(Alpine);

// Free ships English only: choosing a language only switches the layout direction (RTL for Arabic).
Alpine.store("direction", {
    languages: [
        { id: "en", name: "English", shortName: "English", flag: "flag-us.svg" },
        { id: "ar", name: "Arabic (Saudi)", shortName: "Arabic", flag: "flag-sa.svg", badge: "RTL" },
        { id: "es", name: "Español", shortName: "Español", flag: "flag-es.svg" },
        { id: "de", name: "Deutsch", shortName: "Deutsch", flag: "flag-de.svg" },
    ],
    locale: localStorage.getItem("dir") === "rtl" ? "ar" : "en",
    get current() {
        return this.languages.find((l) => l.id === this.locale) || this.languages[0];
    },
    select(id) {
        this.locale = id;
        const dir = id === "ar" ? "rtl" : "ltr";
        localStorage.setItem("dir", dir);
        document.documentElement.setAttribute("dir", dir);
    },
});


Alpine.start();


// Initialize components on DOM ready
document.addEventListener("DOMContentLoaded", () => {
    // Map imports
    if (document.querySelector("#mapOne")) {
        import("./components/maps/world-map").then((module) => module.initMap());
    }
    // Chart imports
    initCharts();

    // Calendar init
    if (document.querySelector("#calendar")) {
        import("./components/calendar/init").then((module) => module.calendarInit());
    }

    // copy button
    const copyInput = document.getElementById("copy-input");
    if (copyInput) {
        // Select the copy button and input field
        const copyButton = document.getElementById("copy-button");
        const copyText = document.getElementById("copy-text");
        const websiteInput = document.getElementById("website-input");

        // Event listener for the copy button
        copyButton.addEventListener("click", () => {
            // Copy the input value to the clipboard
            navigator.clipboard.writeText(websiteInput.value).then(() => {
                // Change the text to "Copied"
                copyText.textContent = "Copied";

                // Reset the text back to "Copy" after 2 seconds
                setTimeout(() => {
                    copyText.textContent = "Copy";
                }, 2000);
            });
        });
    }
});
