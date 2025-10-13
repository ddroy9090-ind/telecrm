<?php
require_once __DIR__ . '/config.php';

$pageScriptFiles = $pageScriptFiles ?? [];
$pageInlineScripts = $pageInlineScripts ?? [];
?>
<!-- Core Libraries -->
<script src="<?= htmlspecialchars(hh_asset('assets/js/jquery-3.7.1.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/js/countrySelect.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<?php foreach ($pageScriptFiles as $script): ?>
    <?php if (is_string($script) && $script !== ''): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php elseif (is_array($script) && isset($script['src'])): ?>
        <script src="<?= htmlspecialchars($script['src'], ENT_QUOTES, 'UTF-8') ?>" <?php
                                                                                    if (!empty($script['attributes']) && is_array($script['attributes'])) {
                                                                                        foreach ($script['attributes'] as $attr => $value) {
                                                                                            if (is_int($attr)) {
                                                                                                echo ' ' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                                                                                            } elseif ($value === true) {
                                                                                                echo ' ' . htmlspecialchars((string) $attr, ENT_QUOTES, 'UTF-8');
                                                                                            } elseif ($value !== false && $value !== null) {
                                                                                                echo ' ' . htmlspecialchars((string) $attr, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                    ?>>
        </script>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Custom JS -->
<script src="<?= htmlspecialchars(hh_asset('assets/js/custom.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.select-dropDownClass').forEach(el => {
            new Choices(el, {
                searchEnabled: false,
                itemSelectText: '',
                shouldSort: false
            });
        });
    });
</script>

<?php foreach ($pageInlineScripts as $inlineScript): ?>
    <?= $inlineScript ?>
<?php endforeach; ?>


<script>
    (function() {
        const section = document.querySelector('.hh-floorplans-01');
        if (!section) return;
        const canvas = section.querySelector('.fp-canvas');
        const buttons = Array.from(section.querySelectorAll('.fp-aside [data-bs-toggle="tab"]'));
        if (!canvas || buttons.length === 0) {
            return;
        }

        function showPane(targetSel) {
            canvas.querySelectorAll('.fp-pane').forEach(p => p.classList.remove('active'));
            const pane = canvas.querySelector(targetSel);
            if (pane) pane.classList.add('active');
        }

        function activateButton(index) {
            buttons.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            const btn = buttons[index];
            if (!btn) {
                return;
            }
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            const targetSel = btn.getAttribute('data-bs-target');
            if (targetSel) {
                showPane(targetSel);
            }
        }

        activateButton(0);

        buttons.forEach((btn, index) => {
            btn.addEventListener('click', function() {
                activateButton(index);
            });
        });

        const lightbox = section.querySelector('.fp-lightbox');
        const lbImg = lightbox ? lightbox.querySelector('img') : null;
        const viewButtons = Array.from(section.querySelectorAll('.fp-view'));
        const planImages = Array.from(section.querySelectorAll('.fp-pane img[data-fp-index]'))
            .map(img => {
                const planIndex = Number(img.getAttribute('data-fp-index'));
                if (Number.isNaN(planIndex)) {
                    return null;
                }
                return {
                    index: planIndex,
                    el: img
                };
            })
            .filter(Boolean)
            .sort((a, b) => a.index - b.index);
        let lbIndex = 0;

        function syncActive(index) {
            activateButton(index);
        }

        function setLightboxImage(position) {
            const item = planImages[position];
            if (!lbImg || !item) {
                return;
            }
            const img = item.el;
            lbImg.src = img.src;
            lbImg.alt = img.alt || 'Floor plan preview';
            lbIndex = position;
            syncActive(item.index);
        }

        function openLightbox(index) {
            if (!lightbox || !lbImg) {
                return;
            }
            const position = planImages.findIndex(item => item.index === index);
            if (position === -1) {
                return;
            }
            lightbox.classList.add('open');
            lightbox.setAttribute('aria-hidden', 'false');
            setLightboxImage(position);
        }

        function closeLightbox() {
            if (!lightbox) {
                return;
            }
            lightbox.classList.remove('open');
            lightbox.setAttribute('aria-hidden', 'true');
        }

        function prevLightbox() {
            if (!planImages.length) return;
            const position = (lbIndex - 1 + planImages.length) % planImages.length;
            setLightboxImage(position);
        }

        function nextLightbox() {
            if (!planImages.length) return;
            const position = (lbIndex + 1) % planImages.length;
            setLightboxImage(position);
        }

        if (lightbox && planImages.length <= 1) {
            lightbox.classList.add('single');
        }

        viewButtons.forEach(btn => {
            btn.addEventListener('click', (event) => {
                const index = Number(btn.getAttribute('data-fp-index'));
                if (!Number.isNaN(index)) {
                    event.stopPropagation();
                    openLightbox(index);
                }
            });
        });

        planImages.forEach(item => {
            const img = item.el;
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', () => {
                openLightbox(item.index);
            });
        });

        if (lightbox) {
            const closeBtn = lightbox.querySelector('.fp-lightbox-close');
            const prevBtn = lightbox.querySelector('.fp-lightbox-nav.prev');
            const nextBtn = lightbox.querySelector('.fp-lightbox-nav.next');

            if (closeBtn) {
                closeBtn.addEventListener('click', closeLightbox);
            }
            if (prevBtn) {
                prevBtn.addEventListener('click', prevLightbox);
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', nextLightbox);
            }

            lightbox.addEventListener('click', (event) => {
                if (event.target === lightbox) {
                    closeLightbox();
                }
            });

            window.addEventListener('keydown', (event) => {
                if (!lightbox.classList.contains('open')) {
                    return;
                }
                if (event.key === 'Escape') {
                    closeLightbox();
                } else if (event.key === 'ArrowLeft') {
                    prevLightbox();
                } else if (event.key === 'ArrowRight') {
                    nextLightbox();
                }
            });
        }
    })();
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var options = {
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            title: {
                text: 'Monthly Property Sales (Demo)',
                align: 'center',
                style: {
                    fontSize: '18px',
                    color: '#004a44'
                }
            },
            series: [{
                name: 'Sales',
                data: [35, 50, 45, 60, 70, 55, 75, 90, 80, 95, 85, 100]
            }],
            xaxis: {
                categories: [
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                ],
                title: {
                    text: 'Month'
                }
            },
            yaxis: {
                title: {
                    text: 'Sales (in AED Thousands)'
                }
            },
            colors: ['#004a44'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    horizontal: false,
                    columnWidth: '45%',
                }
            },
            dataLabels: {
                enabled: true,
                style: {
                    colors: ['#fff']
                }
            },
            grid: {
                borderColor: '#e0e0e0'
            }
        };

        var chart = new ApexCharts(document.querySelector("#barChart"), options);
        chart.render();
    });
</script>

<script>
    var options = {
        series: [{
                name: "High - 2013",
                data: [28, 29, 33, 36, 32, 32, 33]
            },
            {
                name: "Low - 2013",
                data: [12, 11, 14, 18, 17, 13, 13]
            }
        ],
        chart: {
            height: 350,
            type: 'line',
            dropShadow: {
                enabled: true,
                color: '#000',
                top: 18,
                left: 7,
                blur: 10,
                opacity: 0.5
            },
            zoom: {
                enabled: false
            },
            toolbar: {
                show: false
            }
        },
        colors: ['#77B6EA', '#545454'],
        dataLabels: {
            enabled: true,
        },
        stroke: {
            curve: 'smooth'
        },
        title: {
            text: 'Average High & Low Temperature',
            align: 'left'
        },
        grid: {
            borderColor: '#e7e7e7',
            row: {
                colors: ['#f3f3f3', 'transparent'], // takes an array which will be repeated on columns
                opacity: 0.5
            },
        },
        markers: {
            size: 1
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
            title: {
                text: 'Month'
            }
        },
        yaxis: {
            title: {
                text: 'Temperature'
            },
            min: 5,
            max: 40
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            floating: true,
            offsetY: -25,
            offsetX: -5
        }
    };

    var chart = new ApexCharts(document.querySelector("#chart"), options);
    chart.render();
</script>


</body>

</html>