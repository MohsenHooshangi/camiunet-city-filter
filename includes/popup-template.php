<?php
// تنظیم پیش‌فرض برای متغیرها
if (!isset($selected_cities) || !is_array($selected_cities)) {
    $selected_cities = array();
}
?>
<div id="city-selection-popup" class="city-popup-overlay">
    <div class="city-popup-content">
        <div class="city-popup-header" style="justify-content: center;">
            <h3>انتخاب شهر شما</h3>
        </div>
        <div class="city-popup-body">
            <div id="provinces-tab" class="tab-content active">
                <div class="search-box">
                    <input type="text" id="province-search-popup" placeholder="جستجو در استان‌ها..." />
                    <span class="search-icon">🔍</span>
                </div>
                <div id="provinces-list-popup" class="cities-list">
                    <!-- استان‌ها اینجا لود می‌شوند -->
                </div>
            </div>
            <div id="cities-tab" class="tab-content">
                <div class="back-button-container">
                    <button type="button" id="back-to-provinces-popup" class="back-button">
                        ← بازگشت
                    </button>
                    <span id="current-province-name-popup" class="current-province"></span>
                </div>
                <div class="search-box">
                    <input type="text" id="city-search-popup" placeholder="جستجو در شهرها..." />
                    <span class="search-icon">🔍</span>
                </div>
                <div id="cities-list-popup" class="cities-list">
                    <!-- شهرها اینجا لود می‌شوند -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const selectedCities = <?php echo json_encode($selected_cities); ?>;
    let iranProvinces = {};
    let currentProvince = '';

    function getCookie(name) {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            let c = cookies[i].trim();
            if (c.startsWith(name + '=')) {
                return decodeURIComponent(c.split('=')[1]);
            }
        }
        return null;
    }

    let userHasCity = false;

    <?php if (is_user_logged_in()): ?>
        userHasCity = <?php echo json_encode(!empty($selected_cities)); ?>;
        <?php if (!empty($selected_cities) && isset($selected_cities['city'])): ?>
            document.querySelectorAll('.simple-city-selector-btn').forEach(btn => {
                btn.textContent = '<?php echo esc_js($selected_cities['city']); ?>';
            });
        <?php endif; ?>
    <?php else: ?>
        let guestCity = localStorage.getItem('guest_city') || getCookie('guest_city');
        userHasCity = !!guestCity;
        if (userHasCity) {
            selectedCities.province = localStorage.getItem('guest_province') || getCookie('guest_province') || '';
            selectedCities.city = guestCity;
            selectedCities.city_english = localStorage.getItem('guest_city_english') || getCookie('guest_city_english') || '';
            document.querySelectorAll('.simple-city-selector-btn').forEach(btn => {
                btn.textContent = guestCity;
            });
        }
    <?php endif; ?>

    document.getElementById('city-selection-popup').style.display = "none";

    function initPopupAfterLoad() {
        if (        !userHasCity && 
        (window.location.pathname !== "/" && window.location.pathname !== "/index.php")) {
            const popup = document.getElementById('city-selection-popup');
            popup.style.display = "flex";
            popup.style.opacity = 0;
            popup.style.transition = "opacity 0.3s";
            requestAnimationFrame(() => popup.style.opacity = 1);
        }
    }

    function loadPopupData() {
        fetch(citySelector.plugin_url + 'assets/iran-cities.json')
            .then(response => response.json())
            .then(data => {
                iranProvinces = data;
                loadProvincesPopup();
                initPopupAfterLoad();
            })
            .catch(error => {
                console.error('خطا در بارگذاری داده‌های شهرها', error);
            });
    }

    function loadProvincesPopup() {
        const provincesList = document.getElementById('provinces-list-popup');
        provincesList.innerHTML = '';
        Object.keys(iranProvinces).forEach(province => {
            const div = document.createElement('div');
            div.className = 'city-item province-item';

            const span = document.createElement('span');
            span.className = 'province-name';
            span.textContent = province;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'select-province-btn';
            button.dataset.province = province;
            button.textContent = 'انتخاب';

            div.appendChild(span);
            div.appendChild(button);
            provincesList.appendChild(div);
        });
    }

    function showCitiesPopup(province) {
        console.log('شهرهای استان ' + province + ' در حال بارگذاری...');
        document.getElementById('provinces-tab').classList.remove('active');
        document.getElementById('provinces-tab').style.display = 'none';
        document.getElementById('cities-tab').classList.add('active');
        document.getElementById('cities-tab').style.display = 'block';

        document.getElementById('current-province-name-popup').textContent = province;

        const citiesList = document.getElementById('cities-list-popup');
        citiesList.innerHTML = '';

        const cities = iranProvinces[province]?.cities || {};
        Object.keys(cities).forEach(city => {
            const slug = cities[city];
            const div = document.createElement('div');
            div.className = 'city-item';

            const span = document.createElement('span');
            span.className = 'city-name';
            span.textContent = city;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'select-city-btn';
            button.dataset.city = city;
            button.dataset.citySlug = slug;
            button.dataset.province = province;
            button.textContent = 'انتخاب';

            div.appendChild(span);
            div.appendChild(button);
            citiesList.appendChild(div);
        });
    }

    function saveUserCityPopup(province, city, cityEnglish) {
        const formData = new FormData();
        formData.append('action', 'save_user_city');
        formData.append('nonce', citySelector.nonce);
        formData.append('province', province);
        formData.append('city', city);
        formData.append('city_english', cityEnglish);

        fetch(citySelector.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    const pathMatch = window.location.pathname.match(/^\/city\/([^\/]+)(\/.*)?$/i);
                    if (pathMatch) {
                        const rest = pathMatch[2] || '/';
                        const newUrl = '/city/' + cityEnglish.toLowerCase() + rest;
                        window.location.href = newUrl + window.location.search + window.location.hash;
                    } else {
                        window.location.reload();
                    }
                }
            })
            .catch(() => {
                alert('خطا در ذخیره اطلاعات. لطفاً دوباره تلاش کنید.');
            });
    }

    loadPopupData();

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('select-province-btn')) {
            const province = e.target.dataset.province;
            currentProvince = province;
            showCitiesPopup(province);
        }

        if (e.target.classList.contains('select-city-btn')) {
            const city = e.target.dataset.city;
            const slug = e.target.dataset.citySlug;
            const province = e.target.dataset.province;
            saveUserCityPopup(province, city, slug);
        }

        if (e.target.id === 'back-to-provinces-popup') {
            document.getElementById('cities-tab').classList.remove('active');
            document.getElementById('cities-tab').style.display = 'none';
            document.getElementById('provinces-tab').classList.add('active');
            document.getElementById('provinces-tab').style.display = 'block';
        }

        if (e.target.id === 'city-selection-popup') {
            const popup = document.getElementById('city-selection-popup');
            popup.style.opacity = 1;
            popup.style.transition = 'opacity 0.3s';
            popup.style.opacity = 0;
            setTimeout(() => (popup.style.display = 'none'), 300);
        }

        if (e.target.closest('.city-popup-content')) {
            e.stopPropagation();
        }
    });

    document.getElementById('province-search-popup').addEventListener('input', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.province-item').forEach(item => {
            const name = item.querySelector('.province-name').textContent.toLowerCase();
            item.style.display = name.includes(term) ? '' : 'none';
        });
    });

    document.getElementById('city-search-popup').addEventListener('input', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#cities-list-popup .city-item').forEach(item => {
            const name = item.querySelector('.city-name').textContent.toLowerCase();
            item.style.display = name.includes(term) ? '' : 'none';
        });
    });
});

// فراخوانی از بیرون
function openCityPopup() {
    const popup = document.getElementById('city-selection-popup');
    popup.style.display = 'flex';
    popup.style.opacity = 0;
    popup.style.transition = 'opacity 0.3s';
    requestAnimationFrame(() => popup.style.opacity = 1);
}

function closeCityPopup() {
    let userHasCity = false;
    <?php if (is_user_logged_in()): ?>
        userHasCity = <?php echo json_encode(!empty($selected_cities)); ?>;
    <?php else: ?>
        const guestCity = localStorage.getItem('guest_city') || (document.cookie.match(/guest_city=([^;]+)/) || [])[1];
        userHasCity = guestCity !== undefined && guestCity !== null && guestCity !== '';
    <?php endif; ?>
    if (userHasCity) {
        const popup = document.getElementById('city-selection-popup');
        popup.style.opacity = 1;
        popup.style.transition = 'opacity 0.3s';
        popup.style.opacity = 0;
        setTimeout(() => (popup.style.display = 'none'), 300);
    }
}

function skipCitySelection() {
    closeCityPopup();
}
</script>

