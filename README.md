# NovaTools - SEO

**Comprehensive SEO add-on for NovaTools — meta management, schema, sitemaps, redirects, breadcrumbs, and more.**

This is a powerful and professional SEO plugin designed as an add-on for the NovaTools ecosystem. It extends the core capabilities of WordPress and WooCommerce with robust SEO features for store owners and content creators.

> **Note:** This plugin requires the base [NovaTools](https://github.com/novatools) plugin to be installed and activated.

---

## Features

- **Meta Management:** Control SEO titles, descriptions, canonical URLs, and robots meta tags across posts, pages, and custom post types.
- **Advanced Schema Markup:** Automatically generates and outputs JSON-LD schema for Articles, Products, Breadcrumbs, and more, including deep WooCommerce Product Schema integrations.
- **Sitemaps:** Automatically generates an XML sitemap to help search engines easily crawl and index your site content. Includes StockTracker integration for WooCommerce.
- **Redirects Manager:** Manage 301, 302, and 307 redirects easily through the WordPress admin to prevent broken links.
- **IndexNow Integration:** Notifies search engines (like Bing and Yandex) instantly when content is created, updated, or deleted.
- **Google Merchant Center (GMC):** Includes an API/Rest Controller for seamless Google Merchant Center integration.
- **WooCommerce Integrations:** Includes specialized features to optimize WooCommerce stores for SEO:
  - OpenGraph tags specifically for products
  - Primary category selection for products with multiple categories
  - Advanced product filtering SEO (FilterDetector)
  - Taxonomy `noindex` enforcer for unoptimized filter URLs
  - Automated Image Alt Text fallback for products

---

## Tech Stack

The plugin relies on modern technologies for both the backend and frontend:

- **Backend:** Object-Oriented PHP 7.4+, taking advantage of Namespaces, Traits, and standard WordPress APIs. Uses `composer` for dependency management.
- **Admin Frontend:** Built with React 18, React Hook Form, Radix UI primitives, and styled with Tailwind CSS.
- **Build Tooling:** Uses [Vite](https://vitejs.dev/) with `@kucrut/vite-for-wp` to compile React apps directly into WordPress-compatible assets. Code formatting via Prettier and specialized WordPress/Tailwind plugins.

---

## Prerequisites & Installation

### Prerequisites

- PHP >= 7.4
- WordPress >= 5.8
- Base **NovaTools** Plugin must be installed and active.
- Composer (for installing PHP dependencies if cloning from source)
- Node.js (for frontend asset building)

### Installation (End Users)

1. Download the zip release of the plugin (`novatools-seo-x.x.x.zip`).
2. Go to **Plugins > Add New** in your WordPress dashboard.
3. Click **Upload Plugin** and select the zip file.
4. Activate the plugin.

---

## Developer Setup

If you want to contribute to the codebase, follow these steps to set up your local development environment:

1. **Clone the repository** into your local WordPress installations `wp-content/plugins/` directory:
   ```bash
   git clone git@github.com:your-repo/novatools-seo.git
   cd novatools-seo
   ```

2. **Install PHP dependencies** using Composer:
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies** using NPM (or PNPM/Yarn):
   ```bash
   npm install
   ```

4. **Start the development server** to enable Vite Hot Module Replacement (HMR) for the admin UI:
   ```bash
   npm run dev
   ```

5. **Build for production** when you're ready to deploy or commit built assets:
   ```bash
   npm run build
   ```

---

## Directory Structure

A quick overview of the plugin architecture to help developers navigate:

- `novatools-seo.php`: The main plugin file which boots the plugin.
- `plugin.php`: Core plugin class `NovaToolsSEO` housing the initialization logic.
- `includes/`: Core PHP backend source code.
  - `Admin/`: Hooks and callbacks for WP Admin screens and settings.
  - `Frontend/`: Logic for outputting `<head>` tags, robots.txt, and breadcrumbs.
  - `WooCommerce/`: All WooCommerce-specific SEO logic.
  - `Sitemaps/`: XML sitemap generators.
  - `Redirects/`: Redirect matching and management engine.
- `src/`: The React frontend code for the custom NovaTools admin UI settings screens.
  - `src/admin/`: Admin UI components, pages, and layouts.
- `package.json` / `vite.admin.config.js`: Configuration for frontend assets and build scripts.
- `composer.json`: PHP dependency definitions.

---

## Contribution Guidelines

Contributions are welcome! Please adhere to the following guidelines when submitting PRs:

1. **Code Formatting:** The project uses Prettier for the frontend source code. Ensure your code passes formatting checks before committing:
   ```bash
   npm run format:check
   ```
   To automatically fix formatting issues, run:
   ```bash
   npm run format:fix
   ```
2. **PHP Standards:** Follow the WordPress Coding Standards for all PHP files.
3. **Commit Messages:** Write clear, concise commit messages outlining the purpose of the changes.

## License

This project is licensed under the GPLv2 License.
