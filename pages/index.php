<?php

/**
 * Synch Addon - Moderne Key-basierte Synchronisation
 */

$addon = rex_addon::get('synch');

echo rex_view::title($addon->i18n('title'));

rex_be_controller::includeCurrentPageSubPath();