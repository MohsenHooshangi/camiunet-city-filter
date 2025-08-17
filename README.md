# City Filter Plugin – Dynamic City-Based Content for WordPress

A custom **WordPress plugin** that allows users to filter and change site content dynamically based on **selected city and province**.  
This plugin is designed for marketplaces and multi-vendor stores where content, products, and vendors should adapt according to the visitor’s city.  

---

## 🔧 Project Overview

- 👨‍💻 **Role:** Full plugin development (PHP + JS + CSS + AJAX)  
- 📅 **Status:** Completed  
- 🌐 **Live Website:** [camiunet.com](https://www.camiunet.com)  

---

## 🧰 Technologies Used

- **PHP** – Plugin structure, WordPress hooks, and shortcode system.  
- **JavaScript (AJAX)** – Popup city selector, dynamic content updates.  
- **CSS** – Styling for city filter popup and UI components.  
- **JSON** – City and province data (`iran-cities.json`).  

---

## 🧠 Features

- Popup city/province selector with full list of **Iran cities**.  
- Shortcode **`[city_selector_button]`** to display the popup anywhere on the site.  
- Shortcode **`[user-city]`** to dynamically display the selected city in titles, content, or widgets.  
- Updates URL dynamically based on selected city:  
  - Example:  
    - `camiunet.com` → `camiunet.com/city/Isfahan`  
    - `camiunet.com/shop` → `camiunet.com/city/Isfahan/shop`  
- SEO-friendly: generates **city-based URLs** for better search engine visibility.  
- Optional city selection – user can browse without choosing a city.  
- Integrated with Elementor queries (`elementor-city-query.php`).  
- Admin settings page for managing city filter behavior.  

---

## 📂 Project Structure

### **`camiunet-city-filter/`**
- **`camiunet-city-filter.php`** – Main plugin file, registers scripts, styles, and shortcodes.  
- **`elementor-city-query.php`** – Elementor query integration for city-based filtering.  

#### **`includes/`**
- `popup-template.php` – Popup UI template for city/province selection.  

#### **`assets/`**
- `css/city-selection.css` – Styling for popup and buttons.  
- `js/city-meta-box.js` – City filter settings interactions in admin.  
- `js/city-selection.js` – Frontend JS logic for popup and city switching.  
- `iran-cities.json` – JSON database of Iran’s provinces and cities.  

#### **`screenshots/`**
- `City-Selection-Filter-Popup.png` – Popup UI for selecting city.  
- `City-Filter-Bottom.png` – Bottom page filter UI example.  
- `Change-URL.png` – Example of URL change after city selection.  
- `City-Filter-Settings-Sample.JPG` – Admin settings sample page.  

---

## 📸 Screenshots

| Screenshot | Description |
|------------|-------------|
| `City-Selection-Filter-Popup.png` | Popup modal for city selection |
| `City-Filter-Bottom.png` | City filter button on frontend |
| `Change-URL.png` | SEO-friendly URL after city selection |
| `City-Filter-Settings-Sample.JPG` | Admin settings for city filter plugin |

---

## 🗂️ Installation

1. Upload the `camiunet-city-filter` folder to `wp-content/plugins/`.  
2. Activate the plugin from **WordPress Dashboard > Plugins**.  
3. Use `[city_selector_button]` shortcode to add the popup trigger anywhere on your site.  
4. Use `[user-city]` shortcode inside any text/title to display the user’s selected city dynamically.  
5. Verify city-based URLs are generated correctly for SEO.  

---

## 📌 Notes

- Fully compatible with **WordPress v6+ and PHP 8.x**.  
- Works seamlessly with **WooCommerce and Elementor**.  
- Designed for **SEO improvements** through city-specific URLs.  

---

## 📬 Contact

If you’d like to learn more or collaborate:

- 📧 Email: **mr.hooshangi.official@gmail.com**  
- 🌐 Website: [www.mohsenhooshangi.ir](https://www.mohsenhooshangi.ir)  
- 🖥️ GitHub: [github.com/MohsenHooshangi](https://github.com/MohsenHooshangi)  
