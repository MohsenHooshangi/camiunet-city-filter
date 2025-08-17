document.addEventListener('DOMContentLoaded', function () {
    let iranProvinces = {};
    let selectedProvince = '';
    let selectedCity = '';
    let selectedCityEnglish = '';

    // بارگذاری داده‌ها از فایل JSON
    fetch(citySelector.plugin_url + 'assets/iran-cities.json')
        .then(response => {
            if (!response.ok) throw new Error('خطا در بارگذاری');
            return response.json();
        })
        .then(data => {
            iranProvinces = data;
            showProvinces();
        })
        .catch(() => {
            console.error('خطا در بارگذاری داده‌های شهرها');
        });

    // نمایش استان‌ها
    function showProvinces() {
        const provincesGrid = document.getElementById('provinces-grid');
        provincesGrid.innerHTML = '';

        Object.keys(iranProvinces).forEach(function (province) {
            const provinceItem = document.createElement('button');
            provinceItem.className = 'grid-item';
            provinceItem.textContent = province;

            provinceItem.addEventListener('click', function () {
                selectedProvince = province;
                showCities(province);
            });

            provincesGrid.appendChild(provinceItem);
        });
    }

    // نمایش شهرهای استان انتخاب شده
    function showCities(province) {
        document.getElementById('province-selection').style.display = 'none';
        document.getElementById('city-selection').style.display = 'block';
        document.getElementById('selected-province-title').textContent = 'انتخاب شهر در ' + province;

        const citiesGrid = document.getElementById('cities-grid');
        citiesGrid.innerHTML = '';

        const cities = iranProvinces[province].cities;
        Object.keys(cities).forEach(function (city) {
            const cityItem = document.createElement('button');
            cityItem.className = 'grid-item';
            cityItem.textContent = city;

            cityItem.addEventListener('click', function () {
                selectedCity = city;
                selectedCityEnglish = cities[city];
                saveUserCity();
            });

            citiesGrid.appendChild(cityItem);
        });
    }

    // بازگشت به لیست استان‌ها
    document.getElementById('back-to-provinces').addEventListener('click', function () {
        document.getElementById('city-selection').style.display = 'none';
        document.getElementById('province-selection').style.display = 'block';
        selectedProvince = '';
    });

    // ذخیره شهر انتخاب شده
    function saveUserCity() {
        document.getElementById('city-selection').style.display = 'none';
        document.getElementById('loading-step').style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'save_user_city');
        formData.append('nonce', citySelector.nonce);
        formData.append('province', selectedProvince);
        formData.append('city', selectedCity);
        formData.append('city_english', selectedCityEnglish);

        fetch(citySelector.ajax_url, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('city-selection-popup').style.display = 'none';

                    const successDiv = document.createElement('div');
                    successDiv.style.position = 'fixed';
                    successDiv.style.top = '20px';
                    successDiv.style.right = '20px';
                    successDiv.style.background = '#4caf50';
                    successDiv.style.color = 'white';
                    successDiv.style.padding = '15px 20px';
                    successDiv.style.borderRadius = '5px';
                    successDiv.style.zIndex = '10000';
                    successDiv.textContent = 'شهر شما با موفقیت ذخیره شد';

                    document.body.appendChild(successDiv);

                    setTimeout(() => {
                        successDiv.style.transition = 'opacity 0.5s';
                        successDiv.style.opacity = '0';
                        setTimeout(() => document.body.removeChild(successDiv), 500);
                    }, 3000);
                } else {
                    throw new Error();
                }
            })
            .catch(() => {
                alert('خطا در ذخیره اطلاعات. لطفاً دوباره تلاش کنید.');
                document.getElementById('loading-step').style.display = 'none';
                document.getElementById('city-selection').style.display = 'block';
            });
    }
});
