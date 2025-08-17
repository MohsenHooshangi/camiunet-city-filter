jQuery(document).ready(function ($) {
    let iranProvinces = {};
    let selectedCities = [];

    // بارگذاری شهرهای انتخاب شده از فیلدهای مخفی موجود
    function loadSelectedCitiesFromHidden() {
        selectedCities = [];
        $('input[name="selected_cities[]"]').each(function () {
            let citySlug = $(this).val();
            if (citySlug && citySlug.trim() !== '') {
                selectedCities.push(citySlug.trim());
            }
        });
    }

    loadSelectedCitiesFromHidden();

    // بارگذاری داده‌ها از فایل JSON
    $.getJSON(cityMetaBox.plugin_url + 'assets/iran-cities.json', function (data) {
        iranProvinces = data;
        renderFullList();
        updateSelectedCitiesList();
        updateSelectedCount();
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.error('خطا در بارگذاری داده‌های شهرها:', textStatus, errorThrown);
        $('#provinces-container').html('<div class="error-placeholder">خطا در بارگذاری اطلاعات شهرها. لطفاً صفحه را مجدداً بارگذاری کنید.</div>');
    });

    // تغییر نوع پست (سراسری/خاص)
    $(document).on('change', 'input[name="post_city_type"]', function () {
        if ($(this).val() === 'specific') {
            $('#city-selection-area').slideDown(300);
        } else {
            $('#city-selection-area').slideUp(300);
            clearAllSelections();
        }
    });

    // رندر کردن لیست کامل استان‌ها و شهرها
    function renderFullList() {
        const container = $('#provinces-container');
        container.empty();

        if (!iranProvinces || Object.keys(iranProvinces).length === 0) {
            container.html('<div class="loading-placeholder">داده‌ای برای نمایش وجود ندارد.</div>');
            return;
        }

        Object.keys(iranProvinces).forEach(provinceName => {
            const provinceData = iranProvinces[provinceName];
            const cities = provinceData.cities || {};
            const cityKeys = Object.keys(cities);
            const totalCities = cityKeys.length;
            const selectedInProvince = cityKeys.filter(cityKey => selectedCities.includes(cities[cityKey])).length;

            const provinceItem = $(`
                <div class="province-item" data-province-name="${provinceName}">
                    <div class="province-header">
                        <input type="checkbox" class="province-checkbox" data-province-name="${provinceName}">
                        <span class="province-name">${provinceName}</span>
                        <span class="province-count">(${selectedInProvince}/${totalCities})</span>
                        <span class="toggle-arrow"></span>
                    </div>
                    <div class="cities-in-province" style="display: none;"></div>
                </div>
            `);

            const citiesContainer = provinceItem.find('.cities-in-province');
            if (totalCities > 0) {
                cityKeys.forEach(cityName => {
                    const citySlug = cities[cityName];
                    const isSelected = selectedCities.includes(citySlug);
                    const cityCheckbox = $(`
                        <div class="city-item" data-city-slug="${citySlug}">
                            <label>
                                <input type="checkbox" class="city-checkbox" data-city-slug="${citySlug}" data-province-name="${provinceName}" ${isSelected ? 'checked' : ''}>
                                ${cityName}
                            </label>
                        </div>
                    `);
                    citiesContainer.append(cityCheckbox);
                });
            } else {
                citiesContainer.append('<div class="no-data">شهری برای این استان ثبت نشده است.</div>');
            }

            container.append(provinceItem);
            updateProvinceCheckboxState(provinceName);
        });
    }

    // باز/بسته کردن لیست شهرها
    $(document).on('click', '.province-header', function (e) {
        if ($(e.target).is('input')) return; // اگر روی چک‌باکس کلیک شد، کاری نکن
        $(this).siblings('.cities-in-province').slideToggle(200);
        $(this).toggleClass('open');
    });

    // انتخاب/عدم انتخاب یک استان کامل
    $(document).on('change', '.province-checkbox', function () {
        const provinceName = $(this).data('province-name');
        const isChecked = $(this).is(':checked');
        const cities = iranProvinces[provinceName].cities || {};

        Object.values(cities).forEach(citySlug => {
            const index = selectedCities.indexOf(citySlug);
            if (isChecked) {
                if (index === -1) selectedCities.push(citySlug);
            } else {
                if (index > -1) selectedCities.splice(index, 1);
            }
        });

        // Update UI
        $(`.city-checkbox[data-province-name="${provinceName}"]`).prop('checked', isChecked);
        updateAllUI();
    });

    // انتخاب/عدم انتخاب یک شهر
    $(document).on('change', '.city-checkbox', function () {
        const citySlug = $(this).data('city-slug');
        const provinceName = $(this).data('province-name');
        const isChecked = $(this).is(':checked');
        const index = selectedCities.indexOf(citySlug);

        if (isChecked) {
            if (index === -1) selectedCities.push(citySlug);
        } else {
            if (index > -1) selectedCities.splice(index, 1);
        }

        updateProvinceCheckboxState(provinceName);
        updateAllUI();
    });

    // حذف شهر از لیست خلاصه
    $(document).on('click', '.remove-city', function () {
        const citySlug = $(this).data('slug');
        const provinceName = findCityBySlug(citySlug).province;
        const index = selectedCities.indexOf(citySlug);

        if (index > -1) {
            selectedCities.splice(index, 1);
        }

        $(`.city-checkbox[data-city-slug="${citySlug}"]`).prop('checked', false);
        updateProvinceCheckboxState(provinceName);
        updateAllUI();
    });

    // پاک کردن همه انتخاب‌ها
    $('#clear-all-selections').on('click', function () {
        if (selectedCities.length === 0) {
            alert('هیچ شهری انتخاب نشده است');
            return;
        }
        if (confirm('آیا مطمئن هستید که می‌خواهید همه انتخاب‌ها را پاک کنید؟')) {
            clearAllSelections();
            renderFullList(); // Re-render to reset all checkboxes
            updateAllUI();
        }
    });

    function clearAllSelections() {
        selectedCities = [];
        $('.province-checkbox, .city-checkbox').prop('checked', false).prop('indeterminate', false);
    }

    // جستجو
    $('#city-search').on('input', function () {
        const searchTerm = $(this).val().toLowerCase().trim();
        $('.province-item').each(function () {
            const provinceItem = $(this);
            const provinceName = provinceItem.data('province-name').toLowerCase();
            let provinceVisible = false;

            if (provinceName.includes(searchTerm)) {
                provinceVisible = true;
            }

            let anyCityVisible = false;
            provinceItem.find('.city-item').each(function () {
                const cityItem = $(this);
                const cityName = cityItem.text().toLowerCase().trim();
                if (cityName.includes(searchTerm)) {
                    cityItem.show();
                    anyCityVisible = true;
                    provinceVisible = true;
                } else {
                    cityItem.hide();
                }
            });

            if (provinceVisible) {
                provinceItem.show();
                // اگر جستجو داریم و شهرهای داخلش پیدا شدن، بازش کن
                if (searchTerm.length > 0 && anyCityVisible) {
                    provinceItem.find('.cities-in-province').slideDown(100);
                    provinceItem.find('.province-header').addClass('open');
                }
            } else {
                provinceItem.hide();
            }

            // اگر جستجو خالی شد، همه را نشان بده و ببند
            if (searchTerm === '') {
                provinceItem.find('.city-item').show();
                provinceItem.find('.cities-in-province').slideUp(100);
                provinceItem.find('.province-header').removeClass('open');
            }
        });
    });

    // تابع برای به‌روزرسانی وضعیت چک‌باکس استان
    function updateProvinceCheckboxState(provinceName) {
        if (!iranProvinces[provinceName]) return;
        const cities = iranProvinces[provinceName].cities || {};
        const totalCities = Object.keys(cities).length;
        const provinceCheckbox = $(`.province-checkbox[data-province-name="${provinceName}"]`);

        if (totalCities === 0) {
            provinceCheckbox.prop('disabled', true);
            return;
        }

        const selectedInProvinceCount = Object.values(cities).filter(slug => selectedCities.includes(slug)).length;

        $('.province-item[data-province-name="' + provinceName + '"]').find('.province-count').text(`(${selectedInProvinceCount}/${totalCities})`);

        if (selectedInProvinceCount === 0) {
            provinceCheckbox.prop('checked', false).prop('indeterminate', false);
        } else if (selectedInProvinceCount === totalCities) {
            provinceCheckbox.prop('checked', true).prop('indeterminate', false);
        } else {
            provinceCheckbox.prop('checked', false).prop('indeterminate', true);
        }
    }

    // تابع جامع برای به‌روزرسانی کل UI
    function updateAllUI() {
        updateHiddenFields();
        updateSelectedCitiesList();
        updateSelectedCount();
    }

    // به‌روزرسانی فیلدهای مخفی
    function updateHiddenFields() {
        $('#hidden-fields').empty();
        let selectedProvinces = new Set();

        selectedCities.forEach(citySlug => {
            $('#hidden-fields').append(`<input type="hidden" name="selected_cities[]" value="${citySlug}">`);
            const cityData = findCityBySlug(citySlug);
            if (cityData) {
                selectedProvinces.add(cityData.province);
            }
        });

        selectedProvinces.forEach(provinceName => {
            $('#hidden-fields').append(`<input type="hidden" name="selected_provinces[]" value="${provinceName}">`);
        });
    }

    // به‌روزرسانی لیست شهرهای انتخاب شده در پنل خلاصه
    function updateSelectedCitiesList() {
        const list = $('#selected-cities-list');
        list.empty();

        if (selectedCities.length === 0) {
            list.append('<div class="no-selection">هیچ شهری انتخاب نشده است.</div>');
            return;
        }

        selectedCities.forEach(citySlug => {
            const cityData = findCityBySlug(citySlug);
            if (cityData) {
                const item = $(`
                    <div class="selected-city-item" data-slug="${citySlug}">
                        <div class="selected-city-info">
                            <span class="selected-city-name">${cityData.city}</span>
                            <span class="selected-province-name">${cityData.province}</span>
                        </div>
                        <button type="button" class="remove-city" data-slug="${citySlug}" title="حذف">×</button>
                    </div>`);
                list.append(item);
            }
        });
    }

    // به‌روزرسانی تعداد انتخاب شده
    function updateSelectedCount() {
        $('#selected-count').text(selectedCities.length);
    }

    // پیدا کردن شهر بر اساس slug
    function findCityBySlug(slug) {
        for (const province in iranProvinces) {
            const cities = iranProvinces[province].cities;
            for (const city in cities) {
                if (cities[city] === slug) {
                    return { province: province, city: city, slug: slug };
                }
            }
        }
        return null;
    }

    // Validation قبل از ذخیره پست
    $('#post').on('submit', function (e) {
        const postCityType = $('input[name="post_city_type"]:checked').val();
        if (postCityType === 'specific' && selectedCities.length === 0) {
            e.preventDefault();
            alert('لطفاً حداقل یک شهر انتخاب کنید یا گزینه "سراسری" را انتخاب نمایید.');
            $('html, body').animate({
                scrollTop: $('#camiunet_city_filter').offset().top - 50
            }, 500);
            return false;
        }
    });

});



