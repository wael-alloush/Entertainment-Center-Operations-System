/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",
  content: ["./index.html", "./src/**/*.{js,jsx,ts,tsx,css}"],
  theme: {
    extend: {
      colors: {
        primary: "var(--primary)",
        "primary-foreground":
          "var(--primary-foreground, var(--primary-content))",
        background: "var(--background, var(--base-100))",
        foreground: "var(--foreground, var(--base-content))",
        muted: "var(--muted, var(--base-200))",
        destructive: "var(--destructive, var(--error))",
        "destructive-foreground":
          "var(--destructive-foreground, var(--error-content))",
        success: "var(--success)",
        "success-foreground":
          "var(--success-foreground, var(--success-content))",
        warning: "var(--warning)",
        info: "var(--info)",
        border: "var(--border, var(--base-300))",
        ring: "var(--ring, var(--primary))",
      },
    },
  },
  plugins: [],
};
