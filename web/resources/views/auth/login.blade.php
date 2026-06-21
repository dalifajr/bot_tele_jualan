<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Login / Daftar') }} — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <style>
        /* CSS Variables */
        :root {
          --primary-dark: #070d19;
          --text-dark: #0f172a;
          --text-muted: #475569;
          --tw-ring-offset-shadow: 0 0 #0000;
          --tw-ring-shadow: 0 0 #0000;
          --tw-shadow: 0 0 #0000;
        }

        body {
          padding: 1.5rem;
          font-family: "Plus Jakarta Sans", Outfit, sans-serif;
          background-color: var(--primary-dark);
          background-image: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 45%), radial-gradient(circle at 90% 80%, rgba(217, 119, 6, 0.1) 0%, transparent 45%), radial-gradient(circle, rgba(15, 23, 42, 0.95) 0%, rgb(7, 13, 25) 100%);
          background-attachment: fixed;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          overflow-x: hidden;
        }

        *, ::before, ::after {
          --tw-border-spacing-x: 0;
          --tw-border-spacing-y: 0;
          --tw-translate-x: 0;
          --tw-translate-y: 0;
          --tw-rotate: 0;
          --tw-skew-x: 0;
          --tw-skew-y: 0;
          --tw-scale-x: 1;
          --tw-scale-y: 1;
          --tw-scroll-snap-strictness: proximity;
          --tw-ring-offset-width: 0px;
          --tw-ring-offset-color: #fff;
          --tw-ring-color: rgb(59 130 246 / 0.5);
          --tw-ring-offset-shadow: 0 0 #0000;
          --tw-ring-shadow: 0 0 #0000;
          --tw-shadow: 0 0 #0000;
          --tw-shadow-colored: 0 0 #0000;
        }

        *, ::after, ::before {
          border: 0px solid rgb(229, 231, 235);
          box-sizing: border-box;
        }

        body {
          margin: 0px;
          line-height: inherit;
        }

        .ambient-glow-1 {
          position: absolute;
          width: 500px;
          height: 500px;
          background-image: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
          top: -150px;
          left: -100px;
          pointer-events: none;
          z-index: 1;
        }

        .ambient-glow-2 {
          position: absolute;
          width: 500px;
          height: 500px;
          background-image: radial-gradient(circle, rgba(217, 119, 6, 0.05) 0%, transparent 70%);
          bottom: -150px;
          right: -100px;
          pointer-events: none;
          z-index: 1;
        }

        .login-wrapper {
          border-radius: 32px;
          border: 1px solid rgba(255, 255, 255, 0.08);
          background: rgba(255, 255, 255, 0.03);
          position: relative;
          z-index: 10;
          width: 100%;
          max-width: 1100px;
          min-height: 650px;
          backdrop-filter: blur(24px) saturate(120%);
          box-shadow: rgba(0, 0, 0, 0.4) 0px 30px 60px -15px, rgba(59, 130, 246, 0.1) 0px 0px 100px -30px, rgba(255, 255, 255, 0.1) 0px 1px 0px 0px inset;
          display: flex;
          overflow-x: hidden;
          overflow-y: hidden;
        }

        .login-section {
          padding: 3.5rem 4rem;
          border-radius: 32px 0px 0px 32px;
          background: rgba(255, 255, 255, 0.85);
          flex-grow: 1.1;
          flex-shrink: 1;
          flex-basis: 0%;
          backdrop-filter: blur(10px);
          display: flex;
          flex-direction: column;
          justify-content: center;
          position: relative;
          z-index: 2;
          box-shadow: rgba(0, 0, 0, 0.05) 10px 0px 30px -10px;
        }

        .mb-8 {
          margin-bottom: 2rem;
        }

        .flex {
          display: flex;
        }

        .items-center {
          align-items: center;
        }

        .gap-4 {
          row-gap: 1rem;
          column-gap: 1rem;
        }

        .rounded-2xl {
          border-radius: 1rem;
        }

        .border {
          border-top-width: 1px;
          border-right-width: 1px;
          border-bottom-width: 1px;
          border-left-width: 1px;
        }

        .border-slate-100\/80 {
          border-top-color: rgba(241, 245, 249, 0.8);
          border-right-color: rgba(241, 245, 249, 0.8);
          border-bottom-color: rgba(241, 245, 249, 0.8);
          border-left-color: rgba(241, 245, 249, 0.8);
        }

        .bg-white {
          background: rgb(255 255 255 / var(--tw-bg-opacity, 1));
          --tw-bg-opacity: 1;
        }

        .p-2 {
          padding: 0.5rem;
        }

        .shadow-sm {
          --tw-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
          --tw-shadow-colored: 0 1px 2px 0 var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .h-12 {
          height: 3rem;
        }

        .w-auto {
          width: auto;
        }

        .object-contain {
          object-fit: contain;
        }

        .font-outfit {
          font-family: Outfit, sans-serif;
        }

        .text-3xl {
          font-size: 1.875rem;
          line-height: 2.25rem;
        }

        .font-extrabold {
          font-weight: 800;
        }

        .tracking-tight {
          letter-spacing: -0.025em;
        }

        .text-slate-900 {
          --tw-text-opacity: 1;
          color: rgb(15 23 42 / var(--tw-text-opacity, 1));
        }

        .mt-1 {
          margin-top: 0.25rem;
        }

        .text-\[0\.8rem\] {
          font-size: 0.8rem;
        }

        .font-bold {
          font-weight: 700;
        }

        .uppercase {
          text-transform: uppercase;
        }

        .tracking-widest {
          letter-spacing: 0.1em;
        }

        .text-slate-400 {
          --tw-text-opacity: 1;
          color: rgb(148 163 184 / var(--tw-text-opacity, 1));
        }

        .mb-6 {
          margin-bottom: 1.5rem;
        }

        .mb-1 {
          margin-bottom: 0.25rem;
        }

        .text-2xl {
          font-size: 1.5rem;
          line-height: 2rem;
        }

        .text-sm {
          font-size: 0.875rem;
          line-height: 1.25rem;
        }

        .font-medium {
          font-weight: 500;
        }

        .text-slate-500 {
          --tw-text-opacity: 1;
          color: rgb(100 116 139 / var(--tw-text-opacity, 1));
        }

        .relative {
          position: relative;
        }

        .space-y-4 > :not([hidden]) ~ :not([hidden]) {
          --tw-space-y-reverse: 0;
          margin-top: calc(1rem * calc(1 - var(--tw-space-y-reverse)));
          margin-bottom: calc(1rem * var(--tw-space-y-reverse));
        }

        .form-control {
          padding: 0.95rem 1rem 0.95rem 3rem;
          border-radius: 16px;
          border: 1.5px solid rgba(226, 232, 240, 0.8);
          background: rgba(248, 250, 252, 0.65);
          width: 100%;
          font-size: 0.95rem;
          font-weight: 500;
          color: var(--text-dark);
          font-family: inherit;
          transition: all 0.2s;
        }

        .form-control:focus {
          background: rgb(255, 255, 255);
          outline-style: none;
          border-color: rgb(37, 99, 235);
          box-shadow: rgba(37, 99, 235, 0.12) 0px 0px 0px 4px, rgba(37, 99, 235, 0.05) 0px 4px 12px -2px;
        }

        .form-icon {
          position: absolute;
          left: 1.1rem;
          top: 50%;
          transform: translateY(-50%);
          color: rgb(148, 163, 184);
          font-size: 1.05rem;
          z-index: 10;
        }

        .my-4 {
          margin-top: 1rem;
          margin-bottom: 1rem;
        }

        .justify-center {
          justify-content: center;
        }

        .btn-primary {
          padding: 0.95rem;
          border-radius: 16px;
          width: 100%;
          background-image: linear-gradient(135deg, rgb(29, 78, 216) 0%, rgb(30, 64, 175) 50%, rgb(30, 58, 138) 100%);
          background-size: 200%;
          color: rgb(255, 255, 255);
          font-size: 1rem;
          font-weight: 600;
          cursor: pointer;
          box-shadow: rgba(29, 78, 216, 0.3) 0px 10px 20px -5px, rgba(255, 255, 255, 0.1) 0px 0px 0px 1px inset;
          display: flex;
          align-items: center;
          justify-content: center;
          row-gap: 0.5rem;
          column-gap: 0.5rem;
          border: none;
          transition: all 0.2s;
        }

        .btn-primary:hover {
          background-position-x: right;
          background-position-y: center;
          transform: translateY(-2px);
          box-shadow: rgba(29, 78, 216, 0.4) 0px 15px 25px -5px, rgba(59, 130, 246, 0.2) 0px 2px 10px;
        }

        .btn-primary:active {
          transform: translateY(0px);
        }

        .mt-2 {
          margin-top: 0.5rem;
        }

        .text-xs {
          font-size: 0.75rem;
          line-height: 1rem;
        }

        .transition-transform {
          transition-property: transform;
          transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
          transition-duration: 150ms;
        }

        .btn-outline {
          padding: 0.85rem;
          border-radius: 16px;
          border: 1.5px solid rgba(226, 232, 240, 0.8);
          background: rgba(255, 255, 255, 0.4);
          width: 100%;
          color: var(--text-muted);
          font-weight: 600;
          font-size: 0.95rem;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          row-gap: 0.5rem;
          column-gap: 0.5rem;
          transition: all 0.2s;
        }

        .btn-outline:hover {
          background: rgba(37, 99, 235, 0.03);
          border-color: rgb(37, 99, 235);
          color: rgb(37, 99, 235);
          transform: translateY(-1px);
        }

        .mt-3 {
          margin-top: 0.75rem;
        }

        .text-amber-500 {
          --tw-text-opacity: 1;
          color: rgb(245 158 11 / var(--tw-text-opacity, 1));
        }

        .mt-10 {
          margin-top: 2.5rem;
        }

        .border-t {
          border-top-width: 1px;
        }

        .border-slate-200\/80 {
          border-color: rgba(226, 232, 240, 0.8);
        }

        .pt-6 {
          padding-top: 1.5rem;
        }

        .flex-wrap {
          flex-wrap: wrap;
        }

        .justify-between {
          justify-content: space-between;
        }

        .gap-3 {
          row-gap: 0.75rem;
          column-gap: 0.75rem;
        }

        .h-9 {
          height: 2.25rem;
        }

        .rounded-lg {
          border-radius: 0.5rem;
        }

        .border-slate-100 {
          --tw-border-opacity: 1;
          border-color: rgb(241 245 249 / var(--tw-border-opacity, 1));
        }

        .text-left {
          text-align: left;
        }

        .text-\[0\.75rem\] {
          font-size: 0.75rem;
        }

        .tracking-wide {
          letter-spacing: 0.025em;
        }

        .text-slate-800 {
          --tw-text-opacity: 1;
          color: rgb(30 41 59 / var(--tw-text-opacity, 1));
        }

        .text-\[0\.6rem\] {
          font-size: 0.6rem;
        }

        .leading-tight {
          line-height: 1.25;
        }

        .text-right {
          text-align: right;
        }

        .text-\[0\.65rem\] {
          font-size: 0.65rem;
        }

        .info-section {
          padding: 3.5rem;
          border-radius: 0px 32px 32px 0px;
          flex-grow: 1.25;
          flex-shrink: 1;
          flex-basis: 0%;
          background-image: linear-gradient(145deg, rgba(13, 27, 61, 0.75) 0%, rgba(7, 13, 25, 0.9) 100%);
          position: relative;
          display: flex;
          flex-direction: column;
          justify-content: flex-end;
          color: rgb(255, 255, 255);
          overflow-x: hidden;
          overflow-y: hidden;
        }

        .glass-card {
          padding: 1.75rem;
          border-radius: 24px;
          border: 1px solid rgba(255, 255, 255, 0.12);
          background: rgba(255, 255, 255, 0.05);
          backdrop-filter: blur(16px);
          position: relative;
          z-index: 2;
          margin-top: auto;
          box-shadow: rgba(0, 0, 0, 0.3) 0px 20px 40px, rgba(255, 255, 255, 0.1) 0px 1px 0px inset;
          animation-duration: 0.8s;
          animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
          animation-delay: 0s;
          animation-iteration-count: 1;
          animation-direction: normal;
          animation-fill-mode: none;
          animation-play-state: running;
          animation-name: slideUp;
          animation-timeline: auto;
          animation-range-start: normal;
          animation-range-end: normal;
        }

        .w-full {
          width: 100%;
        }

        .glass-header {
          display: flex;
          align-items: center;
          row-gap: 0.75rem;
          column-gap: 0.75rem;
          margin-bottom: 1.25rem;
          font-size: 1.15rem;
          font-weight: 700;
          letter-spacing: -0.01em;
        }

        .text-amber-400 {
          --tw-text-opacity: 1;
          color: rgb(251 191 36 / var(--tw-text-opacity, 1));
        }

        .text-lg {
          font-size: 1.125rem;
          line-height: 1.75rem;
        }

        .mb-4 {
          margin-bottom: 1rem;
        }

        .overflow-hidden {
          overflow-x: hidden;
          overflow-y: hidden;
        }

        .rounded-xl {
          border-radius: 0.75rem;
        }

        .border-white\/5 {
          border-color: rgba(255, 255, 255, 0.05);
        }

        .shadow-inner {
          --tw-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
          --tw-shadow-colored: inset 0 2px 4px 0 var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .glass-image {
          border-radius: 16px;
          border: 1px solid rgba(255, 255, 255, 0.15);
          width: 100%;
          height: auto;
          box-shadow: rgba(0, 0, 0, 0.2) 0px 10px 25px;
          transition: all 0.2s;
        }

        .leading-relaxed {
          line-height: 1.625;
        }

        .text-slate-200\/90 {
          color: rgba(226, 232, 240, 0.9);
        }

        .absolute {
          position: absolute;
        }

        .bottom-4 {
          bottom: 1rem;
        }

        .right-6 {
          right: 1.5rem;
        }

        .text-\[0\.7rem\] {
          font-size: 0.7rem;
        }

        .text-white\/20 {
          color: rgba(255, 255, 255, 0.2);
        }

        .rounded-full {
          border-radius: 9999px;
        }

        .bg-blue-500\/20 {
          background: rgba(59, 130, 246, 0.2);
        }

        .blur-xl {
          --tw-blur: blur(24px);
          filter: var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow);
        }

        .z-10 {
          z-index: 10;
        }

        .border-white\/20 {
          border-color: rgba(255, 255, 255, 0.2);
        }

        .bg-white\/95 {
          background: rgba(255, 255, 255, 0.95);
        }

        .p-3 {
          padding: 0.75rem;
        }

        .shadow-xl {
          --tw-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
          --tw-shadow-colored: 0 20px 25px -5px var(--tw-shadow-color), 0 8px 10px -6px var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .mt-8 {
          margin-top: 2rem;
        }

        .text-slate-200 {
          --tw-text-opacity: 1;
          color: rgb(226 232 240 / var(--tw-text-opacity, 1));
        }

        .fixed {
          position: fixed;
        }

        .inset-0 {
          top: 0px;
          right: 0px;
          bottom: 0px;
          left: 0px;
        }

        .z-\[9999\] {
          z-index: 9999;
        }

        .hidden {
          display: none;
        }

        .bg-black\/70 {
          background: rgba(0, 0, 0, 0.7);
        }

        .p-4 {
          padding: 1rem;
        }

        .h-\[85vh\] {
          height: 85vh;
        }

        .max-w-5xl {
          max-width: 64rem;
        }

        .flex-col {
          flex-direction: column;
        }

        .rounded-3xl {
          border-radius: 1.5rem;
        }

        .border-white\/10 {
          border-color: rgba(255, 255, 255, 0.1);
        }

        .bg-\[\#070d19\] {
          background: rgb(7 13 25 / var(--tw-bg-opacity, 1));
          --tw-bg-opacity: 1;
        }

        .shadow-2xl {
          --tw-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
          --tw-shadow-colored: 0 25px 50px -12px var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .border-b {
          border-bottom-width: 1px;
        }

        .bg-slate-900\/60 {
          background: rgba(15, 23, 42, 0.6);
        }

        .p-5 {
          padding: 1.25rem;
        }

        .backdrop-blur-md {
          --tw-backdrop-blur: blur(12px);
          backdrop-filter: var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia);
        }

        .text-white {
          --tw-text-opacity: 1;
          color: rgb(255 255 255 / var(--tw-text-opacity, 1));
        }

        .bg-slate-900\/50 {
          background: rgba(15, 23, 42, 0.5);
        }

        .transition-all {
          transition-property: all;
          transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
          transition-duration: 150ms;
        }

        .h-5 {
          height: 1.25rem;
        }

        .w-5 {
          width: 1.25rem;
        }

        .flex-1 {
          flex-grow: 1;
          flex-shrink: 1;
          flex-basis: 0%;
        }

        .bg-slate-950 {
          background: rgb(2 6 23 / var(--tw-bg-opacity, 1));
          --tw-bg-opacity: 1;
        }

        .h-full {
          height: 100%;
        }

        .glass-image:hover {
          transform: scale(1.025) translateY(-2px);
          border-color: rgba(255, 255, 255, 0.25);
          box-shadow: rgba(0, 0, 0, 0.3) 0px 15px 30px;
        }

        .hover\:bg-white\/10:hover {
          background: rgba(255, 255, 255, 0.1);
        }

        .hover\:text-white:hover {
          --tw-text-opacity: 1;
          color: rgb(255 255 255 / var(--tw-text-opacity, 1));
        }

        @keyframes slideUp { 
          0% { transform: translateY(30px); opacity: 0; }
          100% { transform: translateY(0px); opacity: 1; }
        }

        /* Reverted Page Loader Overlay */
        #pageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.85);
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(5px);
        }

        #pageLoader.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #1b4ab2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Custom Auth Tabs styles based on user screenshot */
        .auth-tabs-container {
            display: flex;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 16px;
            gap: 4px;
        }

        .auth-tab-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #64748b;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-tab-btn.active {
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        /* ===== Responsive: Tablet & Small Desktop ===== */
        @media (max-width: 968px) {
            body {
                padding: 1rem;
                align-items: flex-start;
                padding-top: 2rem;
            }

            .login-wrapper {
                border-radius: 24px;
                flex-direction: column;
                height: auto;
                min-height: 0px;
                max-width: 100%;
            }

            .login-section {
                padding: 2.5rem 2rem;
                border-radius: 24px;
                box-shadow: none;
            }

            .info-section {
                display: none;
            }
        }

        /* ===== Responsive: Smartphones (425px and below) ===== */
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 0.75rem;
            }

            .ambient-glow-1,
            .ambient-glow-2 {
                width: 300px;
                height: 300px;
            }

            .login-wrapper {
                border-radius: 20px;
                min-height: 0;
                box-shadow: rgba(0, 0, 0, 0.3) 0px 15px 30px -10px, rgba(59, 130, 246, 0.08) 0px 0px 60px -20px;
            }

            .login-section {
                padding: 1.75rem 1.25rem;
                border-radius: 20px;
            }

            .mb-8 {
                margin-bottom: 1.25rem;
            }

            .mb-4 {
                margin-bottom: 0.75rem;
            }

            .text-3xl {
                font-size: 1.5rem;
                line-height: 1.8rem;
            }

            .text-2xl {
                font-size: 1.25rem;
                line-height: 1.6rem;
            }

            .text-\[0\.8rem\] {
                font-size: 0.7rem;
            }

            .form-control {
                padding: 0.8rem 0.85rem 0.8rem 2.6rem;
                border-radius: 14px;
                font-size: 0.88rem;
            }

            .form-icon {
                left: 0.9rem;
                font-size: 0.95rem;
            }

            .btn-primary {
                padding: 0.8rem;
                border-radius: 14px;
                font-size: 0.92rem;
            }

            .btn-outline {
                padding: 0.75rem;
                border-radius: 14px;
                font-size: 0.88rem;
            }

            .auth-tabs-container {
                padding: 4px;
                border-radius: 14px;
            }

            .auth-tab-btn {
                padding: 10px;
                border-radius: 10px;
                font-size: 0.88rem;
            }

            .mt-10 {
                margin-top: 1.5rem;
            }

            .pt-6 {
                padding-top: 1rem;
            }

            .gap-4 {
                gap: 0.75rem;
            }

            .space-y-4 > :not([hidden]) ~ :not([hidden]) {
                margin-top: 0.75rem;
            }

            .row.g-2 > [class*="col-"] {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body style="cursor: default;">

  <!-- Ambient Backdrops -->
  <div class="ambient-glow-1"></div>
  <div class="ambient-glow-2"></div>

  <!-- Reverted Simple Loader Overlay -->
  <div id="pageLoader">
    <div class="spinner"></div>
  </div>

  <div class="login-wrapper">
    <!-- Left Section: Form -->
    <div class="login-section">
      <!-- Logo & Brand (Overlapping Circles Logo Lama) -->
      <div class="brand-header mb-8">
        <div class="flex items-center gap-4">
          <div class="flex items-center">
            <div class="rounded-full flex items-center justify-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #1b4ab2 0%, #1565c0 100%); z-index: 2;">
              <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="rounded-full flex items-center justify-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #00d2ff 0%, #00a8cc 100%); margin-left: -12px; border: 3px solid rgba(255,255,255,0.85); z-index: 1;">
              <span class="font-bold" style="font-size: 0.65rem;">Mitra</span>
            </div>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 font-outfit" style="line-height: 1; margin: 0;">Jualan</h1>
            <div class="text-[0.8rem] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight mb-1" id="auth-title">Selamat Datang</h2>
        <p class="text-sm text-slate-500 font-medium" id="auth-subtitle">Silakan masuk untuk mengakses portal Anda.</p>
      </div>

      <!-- Auth Tabs Navigation (Pill Selector) -->
      <div class="auth-tabs-container mb-4">
          <button type="button" class="auth-tab-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">Masuk</button>
          <button type="button" class="auth-tab-btn" id="tab-btn-register" onclick="switchAuthTab('register')">Daftar</button>
      </div>

      <!-- Alerts Block -->
      @if(session('error'))
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
      @endif
      @if(session('success'))
          <div class="alert alert-success py-2 small border-0 bg-success bg-opacity-10 text-success rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
      @endif
      @if($errors->any())
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <ul class="mb-0 ps-3">
                  @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <!-- Hidden Tab Pillars -->
      <ul class="nav nav-pills d-none" id="authTabs" role="tablist">
          <li class="nav-item">
              <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">Masuk</button>
          </li>
          <li class="nav-item">
              <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">Daftar</button>
          </li>
      </ul>

      <div class="tab-content" id="authTabsContent">
          
          {{-- TAB MASUK (LOGIN) --}}
          <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
              <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                  @csrf
                  <div class="form-group relative">
                      <input type="text" name="login" class="form-control" placeholder="Username atau Email" value="{{ old('login') }}" required autofocus />
                      <i class="fas fa-user form-icon"></i>
                  </div>
                  <div class="form-group relative">
                      <input type="password" name="password" class="form-control" placeholder="Password" required />
                      <i class="fas fa-lock form-icon"></i>
                  </div>

                  <div class="flex justify-between items-center">
                      <div class="form-check ms-1">
                          <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                          <label class="form-check-label small text-muted" for="rememberCheck">Ingat Saya</label>
                      </div>
                  </div>

                  <!-- Cloudflare Turnstile -->
                  <div class="flex justify-center my-4">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" class="btn-primary mt-2">
                      <span>Masuk Sistem</span>
                      <i class="fas fa-arrow-right text-xs transition-transform"></i>
                  </button>
              </form>

              <div class="position-relative my-4 text-center">
                  <hr class="text-secondary opacity-25">
                  <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 small text-muted font-bold" style="font-size: 0.75rem;">ATAU</span>
              </div>

              <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                  @csrf
                  <button type="submit" class="btn-outline w-100">
                      <i class="fab fa-telegram text-info fs-5"></i> Login dengan Telegram
                  </button>
              </form>
          </div>

          {{-- TAB DAFTAR (REGISTER) --}}
          <div class="tab-pane fade" id="register-pane" role="tabpanel">
              <form action="{{ route('register.post') }}" method="POST" class="space-y-4">
                  @csrf
                  <div class="form-group relative">
                      <input type="text" name="full_name" class="form-control" placeholder="Nama Lengkap" value="{{ old('full_name') }}" required />
                      <i class="fas fa-id-card form-icon"></i>
                  </div>

                  <div class="row g-2">
                      <div class="col-6">
                          <div class="form-group relative">
                              <input type="text" name="username" class="form-control" placeholder="Username" value="{{ old('username') }}" required />
                              <i class="fas fa-user-circle form-icon"></i>
                          </div>
                      </div>
                      <div class="col-6">
                          <div class="form-group relative">
                              <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" />
                              <i class="fas fa-envelope form-icon"></i>
                          </div>
                      </div>
                  </div>

                  <div class="form-group relative">
                      <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                      <i class="fab fa-telegram-plane form-icon"></i>
                      <!-- Shrunk helper text positioned cleanly inside the form-group relative container -->
                      <div id="telegram_id_feedback" class="text-slate-400 font-medium ms-2 mt-1" style="font-size: 0.65rem;">Agar bisa otomatis login dengan Telegram nantinya.</div>
                  </div>

                  <div class="row g-2">
                      <div class="col-6">
                          <div class="form-group relative">
                              <input type="password" name="password" class="form-control" placeholder="Kata Sandi" required />
                              <i class="fas fa-key form-icon"></i>
                          </div>
                      </div>
                      <div class="col-6">
                          <div class="form-group relative">
                              <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi Sandi" required />
                              <i class="fas fa-lock form-icon"></i>
                          </div>
                      </div>
                  </div>

                  <!-- Cloudflare Turnstile -->
                  <div class="flex justify-center my-4">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" id="btn-register" class="btn-primary mt-2">
                      <span>Daftar Sekarang</span>
                      <i class="fas fa-user-plus text-xs"></i>
                  </button>
              </form>
          </div>
      </div>

      <!-- Footer / Supported Info -->
      <div class="mt-10 pt-6 border-t border-slate-200/80">
        <div class="flex items-center justify-between gap-4 flex-wrap">
          <div class="text-left">
            <div class="text-[0.75rem] text-slate-400 font-medium">© 2026</div>
            <div class="text-[0.65rem] font-bold text-slate-500">dzulfikrialifajri_store</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Section: Info & Showcase -->
    <div class="info-section">
      <!-- Floating Glass Announcement Card -->
      <div class="glass-card w-full">
        <div class="glass-header text-amber-400">
          <i class="fas fa-bullhorn text-lg"></i>
          <span>Informasi Store</span>
        </div>
        
        <p class="text-sm font-medium text-slate-200/90 leading-relaxed mb-4">
          {!! $announcement !!}
        </p>

        <!-- Kontak Admin Box (Translucent Glass Overlay) -->
        <div class="small mb-4 p-3 rounded-4 border" style="background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1) !important; color: #fff;">
            <strong class="d-block mb-2 text-white">Kontak Admin:</strong>
            <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none mt-1 d-block"><i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WA</a>
            <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none mt-1 d-block"><i class="fab fa-telegram text-info me-1"></i> @dzulfikrialifajri - Telegram</a>
        </div>

        <div class="flex justify-between items-center">
            <span class="badge bg-white bg-opacity-10 text-white rounded-pill py-2 px-3 shadow-sm border border-white border-opacity-10" style="font-size: 0.72rem;">
                <i class="fas fa-users me-1 text-info"></i> Pengunjung Hari Ini: {{ $todayVisitors ?? 0 }}
            </span>
        </div>
      </div>
      <div class="absolute bottom-4 right-6 text-[0.7rem] font-bold tracking-widest text-white/20 uppercase">Jualan v2.0</div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      function switchAuthTab(tabName) {
          const loginBtn = document.getElementById('tab-btn-login');
          const registerBtn = document.getElementById('tab-btn-register');
          
          if (tabName === 'register') {
              document.getElementById('auth-title').innerText = 'Daftar Akun Baru';
              document.getElementById('auth-subtitle').innerText = 'Silakan isi formulir untuk mendaftar sebagai pelanggan.';
              
              loginBtn.classList.remove('active');
              registerBtn.classList.add('active');
              
              var tab = new bootstrap.Tab(document.getElementById('register-tab'));
              tab.show();
          } else {
              document.getElementById('auth-title').innerText = 'Selamat Datang';
              document.getElementById('auth-subtitle').innerText = 'Silakan masuk untuk mengakses portal Anda.';
              
              registerBtn.classList.remove('active');
              loginBtn.classList.add('active');
              
              var tab = new bootstrap.Tab(document.getElementById('login-tab'));
              tab.show();
          }
      }

      // Auto open register tab on error with register input
      document.addEventListener('DOMContentLoaded', function() {
          @if(old('username') && $errors->any())
              switchAuthTab('register');
          @endif
      });

      // Real-time Telegram ID availability check
      document.addEventListener('DOMContentLoaded', function() {
          let telegramInput = document.getElementById('telegram_id_reg');
          if (!telegramInput) return;
          
          let feedbackElem = document.getElementById('telegram_id_feedback');
          let defaultFeedback = 'Agar bisa otomatis login dengan Telegram nantinya.';
          let saveBtn = document.getElementById('btn-register');
          let checkTimeout;

          telegramInput.addEventListener('input', function() {
              clearTimeout(checkTimeout);
              let val = this.value.trim();

              if (!val) {
                  feedbackElem.innerHTML = defaultFeedback;
                  saveBtn.disabled = false;
                  return;
              }

              feedbackElem.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Mengecek...</span>';
              saveBtn.disabled = true;

              checkTimeout = setTimeout(() => {
                  fetch('{{ route("api.check.telegram") }}', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json',
                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                          'Accept': 'application/json'
                      },
                      body: JSON.stringify({ telegram_id: val })
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.available) {
                          feedbackElem.innerHTML = `<span class="text-success"><i class="fas fa-check-circle me-1"></i>${data.message}</span>`;
                          saveBtn.disabled = false;
                      } else {
                          feedbackElem.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${data.message}</span>`;
                          saveBtn.disabled = true;
                      }
                  })
                  .catch(err => {
                      console.error(err);
                      feedbackElem.innerHTML = '<span class="text-danger">Gagal mengecek ID Telegram.</span>';
                      saveBtn.disabled = false; 
                  });
              }, 500);
          });
      });
  </script>
  <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
