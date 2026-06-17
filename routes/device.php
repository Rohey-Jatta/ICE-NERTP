<?php

use Illuminate\Support\Facades\Route;

/**
 * Device routes — kept minimal.
 * The old /auth/device/verify and /auth/device/register routes are removed.
 * Device registration now happens silently in TwoFactorController::verify().
 * This file is kept only for potential future admin-facing device management.
 */

// No public device registration routes needed.
// All device binding is handled server-side in TwoFactorController.