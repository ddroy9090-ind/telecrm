<?php
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

    <?php foreach ($pageScriptFiles as $script): ?>
        <?php if (is_string($script) && $script !== ''): ?>
            <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php elseif (is_array($script) && isset($script['src'])): ?>
            <script src="<?= htmlspecialchars($script['src'], ENT_QUOTES, 'UTF-8') ?>"<?php
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

    </body>

    </html>