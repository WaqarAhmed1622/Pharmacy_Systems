<?php
/**
 * Header Include File
 * Contains HTML head section and navigation
 */

// Determine the correct path to includes based on current file location
$auth_path = '';
$functions_path = '';

date_default_timezone_set('Asia/Karachi');

if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    // Called from pages directory
    $auth_path = '../includes/auth.php';
    $functions_path = '../includes/functions.php';
} else {
    // Called from root directory
    $auth_path = 'includes/auth.php';
    $functions_path = 'includes/functions.php';
}

// Include required files
if (file_exists($auth_path)) {
    require_once $auth_path;
} else {
    die('Authentication file not found. Please ensure includes/auth.php exists.');
}

if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    die('Functions file not found. Please ensure includes/functions.php exists.');
}

// Check if user is logged in
requireLogin();
checkSessionTimeout();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management System</title>
    <link rel="icon" href="../assets/logo.jpg" type="image/x-icon">
    
    <!-- Embedded Bootstrap CSS (Offline) -->
    <style>
        /* Bootstrap 5.1.3 Core Styles - Embedded for Offline Use */
        *,::after,::before{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;-webkit-text-size-adjust:100%;-webkit-tap-highlight-color:transparent}hr{margin:1rem 0;color:inherit;background-color:currentColor;border:0;opacity:.25}hr:not([size]){height:1px}h1,h2,h3,h4,h5,h6{margin-top:0;margin-bottom:.5rem;font-weight:500;line-height:1.2}h1{font-size:calc(1.375rem + 1.5vw)}@media (min-width:1200px){h1{font-size:2.5rem}}h2{font-size:calc(1.325rem + .9vw)}@media (min-width:1200px){h2{font-size:2rem}}h3{font-size:calc(1.3rem + .6vw)}@media (min-width:1200px){h3{font-size:1.75rem}}h4{font-size:calc(1.275rem + .3vw)}@media (min-width:1200px){h4{font-size:1.5rem}}h5{font-size:1.25rem}h6{font-size:1rem}p{margin-top:0;margin-bottom:1rem}
        .container,.container-fluid{width:100%;padding-right:var(--bs-gutter-x,.75rem);padding-left:var(--bs-gutter-x,.75rem);margin-right:auto;margin-left:auto}@media (min-width:576px){.container{max-width:540px}}@media (min-width:768px){.container{max-width:720px}}@media (min-width:992px){.container{max-width:960px}}@media (min-width:1200px){.container{max-width:1140px}}@media (min-width:1400px){.container{max-width:1320px}}
        .row{--bs-gutter-x:1.5rem;--bs-gutter-y:0;display:flex;flex-wrap:wrap;margin-top:calc(-1 * var(--bs-gutter-y));margin-right:calc(-.5 * var(--bs-gutter-x));margin-left:calc(-.5 * var(--bs-gutter-x))}.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x) * .5);padding-left:calc(var(--bs-gutter-x) * .5);margin-top:var(--bs-gutter-y)}
        .col{flex:1 0 0%}.col-md-3{flex:0 0 auto;width:25%}.col-md-4{flex:0 0 auto;width:33.33333333%}.col-md-6{flex:0 0 auto;width:50%}.col-md-8{flex:0 0 auto;width:66.66666667%}.col-md-9{flex:0 0 auto;width:75%}.col-lg-2{flex:0 0 auto;width:16.66666667%}.col-lg-10{flex:0 0 auto;width:83.33333333%}.col-12{flex:0 0 auto;width:100%}
        .btn{display:inline-block;font-weight:400;line-height:1.5;color:#212529;text-align:center;text-decoration:none;vertical-align:middle;cursor:pointer;-webkit-user-select:none;-moz-user-select:none;user-select:none;background-color:transparent;border:1px solid transparent;padding:.375rem .75rem;font-size:1rem;border-radius:.25rem;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out}
        .btn-primary{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.btn-primary:hover{color:#fff;background-color:#0b5ed7;border-color:#0a58ca}.btn-success{color:#fff;background-color:#198754;border-color:#198754}.btn-success:hover{color:#fff;background-color:#157347;border-color:#146c43}.btn-danger{color:#fff;background-color:#dc3545;border-color:#dc3545}.btn-danger:hover{color:#fff;background-color:#bb2d3b;border-color:#b02a37}.btn-warning{color:#000;background-color:#ffc107;border-color:#ffc107}.btn-warning:hover{color:#000;background-color:#ffca2c;border-color:#ffc720}.btn-info{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}.btn-info:hover{color:#000;background-color:#31d2f2;border-color:#25cff2}.btn-secondary{color:#fff;background-color:#6c757d;border-color:#6c757d}.btn-secondary:hover{color:#fff;background-color:#5c636a;border-color:#565e64}
        .btn-outline-primary{color:#0d6efd;border-color:#0d6efd}.btn-outline-primary:hover{color:#fff;background-color:#0d6efd;border-color:#0d6efd}.btn-outline-secondary{color:#6c757d;border-color:#6c757d}.btn-outline-secondary:hover{color:#fff;background-color:#6c757d;border-color:#6c757d}.btn-outline-success{color:#198754;border-color:#198754}.btn-outline-success:hover{color:#fff;background-color:#198754;border-color:#198754}.btn-outline-danger{color:#dc3545;border-color:#dc3545}.btn-outline-danger:hover{color:#fff;background-color:#dc3545;border-color:#dc3545}.btn-outline-warning{color:#ffc107;border-color:#ffc107}.btn-outline-warning:hover{color:#000;background-color:#ffc107;border-color:#ffc107}.btn-outline-info{color:#0dcaf0;border-color:#0dcaf0}.btn-outline-info:hover{color:#000;background-color:#0dcaf0;border-color:#0dcaf0}
        .btn-sm{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.btn-lg{padding:.5rem 1rem;font-size:1.25rem;border-radius:.3rem}.btn-group{position:relative;display:inline-flex;vertical-align:middle}.btn-group>.btn{position:relative;flex:1 1 auto}.btn-group>.btn-check:checked+.btn,.btn-group>.btn-check:focus+.btn,.btn-group>.btn.active,.btn-group>.btn:active,.btn-group>.btn:focus,.btn-group>.btn:hover{z-index:1}.btn-group .btn+.btn,.btn-group .btn+.btn-group,.btn-group .btn-group+.btn,.btn-group .btn-group+.btn-group{margin-left:-1px}.btn-group>.btn:not(:last-child):not(.dropdown-toggle),.btn-group>.btn-group:not(:last-child)>.btn{border-top-right-radius:0;border-bottom-right-radius:0}.btn-group>.btn:nth-child(n+3),.btn-group>:not(.btn-check)+.btn,.btn-group>.btn-group:not(:first-child)>.btn{border-top-left-radius:0;border-bottom-left-radius:0}
        .form-control{display:block;width:100%;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-image:none;border:1px solid #ced4da;-webkit-appearance:none;-moz-appearance:none;appearance:none;border-radius:.25rem;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out}.form-control:focus{color:#212529;background-color:#fff;border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.form-select{display:block;width:100%;padding:.375rem 2.25rem .375rem .75rem;-moz-padding-start:calc(.75rem - 3px);font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px;border:1px solid #ced4da;border-radius:.25rem;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out;-webkit-appearance:none;-moz-appearance:none;appearance:none}.form-select:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}
        .form-label{margin-bottom:.5rem}.form-check{display:block;min-height:1.5rem;padding-left:1.5em;margin-bottom:.125rem}.form-check .form-check-input{float:left;margin-left:-1.5em}.form-check-input{width:1em;height:1em;margin-top:.25em;vertical-align:top;background-color:#fff;background-repeat:no-repeat;background-position:center;background-size:contain;border:1px solid rgba(0,0,0,.25);-webkit-appearance:none;-moz-appearance:none;appearance:none;-webkit-print-color-adjust:exact;color-adjust:exact}.form-check-input[type=checkbox]{border-radius:.25em}.form-check-input:active{filter:brightness(90%)}.form-check-input:focus{border-color:#86b7fe;outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25)}.form-check-input:checked{background-color:#0d6efd;border-color:#0d6efd}.form-check-input:checked[type=checkbox]{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e")}.form-check-label{color:#212529;cursor:pointer}
        .input-group{position:relative;display:flex;flex-wrap:wrap;align-items:stretch;width:100%}.input-group>.form-control,.input-group>.form-select{position:relative;flex:1 1 auto;width:1%;min-width:0}.input-group>.form-control:focus,.input-group>.form-select:focus{z-index:3}.input-group .btn{position:relative;z-index:2}.input-group .btn:focus{z-index:3}.input-group-text{display:flex;align-items:center;padding:.375rem .75rem;font-size:1rem;font-weight:400;line-height:1.5;color:#212529;text-align:center;white-space:nowrap;background-color:#e9ecef;border:1px solid #ced4da;border-radius:.25rem}.input-group:not(.has-validation)>.dropdown-toggle:nth-last-child(n+3),.input-group:not(.has-validation)>:not(:last-child):not(.dropdown-toggle):not(.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.input-group.has-validation>.dropdown-toggle:nth-last-child(n+4),.input-group.has-validation>:nth-last-child(n+3):not(.dropdown-toggle):not(.dropdown-menu){border-top-right-radius:0;border-bottom-right-radius:0}.input-group>:not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback){margin-left:-1px;border-top-left-radius:0;border-bottom-left-radius:0}
        .card{position:relative;display:flex;flex-direction:column;min-width:0;word-wrap:break-word;background-color:#fff;background-clip:border-box;border:1px solid rgba(0,0,0,.125);border-radius:.25rem}.card>hr{margin-right:0;margin-left:0}.card>.list-group{border-top:inherit;border-bottom:inherit}.card>.list-group:first-child{border-top-width:0;border-top-left-radius:calc(.25rem - 1px);border-top-right-radius:calc(.25rem - 1px)}.card>.list-group:last-child{border-bottom-width:0;border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}.card>.card-header+.list-group,.card>.list-group+.card-footer{border-top:0}.card-body{flex:1 1 auto;padding:1rem 1rem}.card-title{margin-bottom:.5rem}.card-header{padding:.5rem 1rem;margin-bottom:0;background-color:rgba(0,0,0,.03);border-bottom:1px solid rgba(0,0,0,.125)}.card-header:first-child{border-radius:calc(.25rem - 1px) calc(.25rem - 1px) 0 0}
        .table{--bs-table-bg:transparent;--bs-table-accent-bg:transparent;--bs-table-striped-color:#212529;--bs-table-striped-bg:rgba(0, 0, 0, 0.05);--bs-table-active-color:#212529;--bs-table-active-bg:rgba(0, 0, 0, 0.1);--bs-table-hover-color:#212529;--bs-table-hover-bg:rgba(0, 0, 0, 0.075);width:100%;margin-bottom:1rem;color:#212529;vertical-align:top;border-color:#dee2e6}.table>:not(caption)>*>*{padding:.5rem .5rem;background-color:var(--bs-table-bg);border-bottom-width:1px;box-shadow:inset 0 0 0 9999px var(--bs-table-accent-bg)}.table>tbody{vertical-align:inherit}.table>thead{vertical-align:bottom}.table>:not(:first-child){border-top:2px solid currentColor}.table-striped>tbody>tr:nth-of-type(odd)>*{--bs-table-accent-bg:var(--bs-table-striped-bg);color:var(--bs-table-striped-color)}.table-responsive{overflow-x:auto}.table-light{--bs-table-bg:#f8f9fa;--bs-table-striped-bg:#ecedee;--bs-table-striped-color:#000;--bs-table-active-bg:#dfe0e1;--bs-table-active-color:#000;--bs-table-hover-bg:#e5e6e7;--bs-table-hover-color:#000;color:#000;border-color:#dfe0e1}.table-success{--bs-table-bg:#d1e7dd;--bs-table-striped-bg:#c7dbd2;--bs-table-striped-color:#000;--bs-table-active-bg:#bcd0c7;--bs-table-active-color:#000;--bs-table-hover-bg:#c1d6cc;--bs-table-hover-color:#000;color:#000;border-color:#bcd0c7}.table-danger{--bs-table-bg:#f8d7da;--bs-table-striped-bg:#eccccf;--bs-table-striped-color:#000;--bs-table-active-bg:#dfc2c4;--bs-table-active-color:#000;--bs-table-hover-bg:#e5c7ca;--bs-table-hover-color:#000;color:#000;border-color:#dfc2c4}.table-warning{--bs-table-bg:#fff3cd;--bs-table-striped-bg:#f2e7c3;--bs-table-striped-color:#000;--bs-table-active-bg:#e6dbb9;--bs-table-active-color:#000;--bs-table-hover-bg:#ece1be;--bs-table-hover-color:#000;color:#000;border-color:#e6dbb9}.table-secondary{--bs-table-bg:#e2e3e5;--bs-table-striped-bg:#d7d8da;--bs-table-striped-color:#000;--bs-table-active-bg:#cbccce;--bs-table-active-color:#000;--bs-table-hover-bg:#d1d2d4;--bs-table-hover-color:#000;color:#000;border-color:#cbccce}
        .alert{position:relative;padding:.75rem 1.25rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.25rem}.alert-success{color:#0a3622;background-color:#d1e7dd;border-color:#badbcc}.alert-danger{color:#58151c;background-color:#f8d7da;border-color:#f1aeb5}.alert-warning{color:#66512c;background-color:#fff3cd;border-color:#ffe69c}.alert-dismissible{padding-right:3rem}.alert-dismissible .btn-close{position:absolute;top:0;right:0;z-index:2;padding:.9375rem 1.25rem}
        .badge{display:inline-block;padding:.35em .65em;font-size:.75em;font-weight:700;line-height:1;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.25rem}.badge:empty{display:none}.btn .badge{position:relative;top:-1px}.badge.bg-primary{background-color:#0d6efd!important}.badge.bg-secondary{background-color:#6c757d!important}.badge.bg-success{background-color:#198754!important}.badge.bg-danger{background-color:#dc3545!important}.badge.bg-warning{color:#000!important;background-color:#ffc107!important}.badge.bg-info{color:#000!important;background-color:#0dcaf0!important}
        .list-group{display:flex;flex-direction:column;padding-left:0;margin-bottom:0;border-radius:.25rem}.list-group-item{position:relative;display:block;padding:.5rem 1rem;color:#212529;text-decoration:none;background-color:#fff;border:1px solid rgba(0,0,0,.125)}.list-group-item:first-child{border-top-left-radius:inherit;border-top-right-radius:inherit}.list-group-item:last-child{border-bottom-right-radius:inherit;border-bottom-left-radius:inherit}.list-group-item.disabled,.list-group-item:disabled{color:#6c757d;pointer-events:none;background-color:#fff}.list-group-item.active{z-index:2;color:#fff;background-color:#0d6efd;border-color:#0d6efd}.list-group-item+.list-group-item{border-top-width:0}.list-group-item+.list-group-item.active{margin-top:-1px;border-top-width:1px}.list-group-flush{border-radius:0}.list-group-flush>.list-group-item{border-width:0 0 1px}.list-group-flush>.list-group-item:last-child{border-bottom-width:0}
        .nav{display:flex;flex-wrap:wrap;padding-left:0;margin-bottom:0;list-style:none}.nav-link{display:block;padding:.5rem 1rem;color:#0d6efd;text-decoration:none;transition:color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out}.nav-link:focus,.nav-link:hover{color:#0a58ca}.nav-link.disabled{color:#6c757d;pointer-events:none;cursor:default}.nav.flex-column{flex-direction:column}
        .d-inline{display:inline!important}.d-inline-block{display:inline-block!important}.d-block{display:block!important}.d-grid{display:grid!important}.d-flex{display:flex!important}.justify-content-between{justify-content:space-between!important}.align-items-center{align-items:center!important}.gap-2{gap:.5rem!important}.me-2{margin-right:.5rem!important}.me-3{margin-right:1rem!important}.ms-1{margin-left:.25rem!important}.mb-0{margin-bottom:0!important}.mb-1{margin-bottom:.25rem!important}.mb-2{margin-bottom:.5rem!important}.mb-3{margin-bottom:1rem!important}.mb-4{margin-bottom:1.5rem!important}.mt-2{margin-top:.5rem!important}.mt-3{margin-top:1rem!important}.mt-4{margin-top:1.5rem!important}.py-4{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.py-5{padding-top:3rem!important;padding-bottom:3rem!important}.px-0{padding-right:0!important;padding-left:0!important}.px-3{padding-right:1rem!important;padding-left:1rem!important}.text-center{text-align:center!important}.text-end{text-align:end!important}.text-muted{color:#6c757d!important}.text-primary{color:#0d6efd!important}.text-success{color:#198754!important}.text-danger{color:#dc3545!important}.text-warning{color:#ffc107!important}.text-info{color:#0dcaf0!important}.bg-primary{background-color:#0d6efd!important}.bg-secondary{background-color:#6c757d!important}.bg-success{background-color:#198754!important}.bg-danger{background-color:#dc3545!important}.bg-warning{background-color:#ffc107!important}.bg-info{background-color:#0dcaf0!important}.bg-light{background-color:#f8f9fa!important}.bg-danger-subtle{background-color:#f8d7da!important}.bg-warning-subtle{background-color:#fff3cd!important}.w-100{width:100%!important}.h-100{height:100%!important}.small{font-size:.875em}code{font-size:.875em;color:#d63384;word-wrap:break-word}hr{margin:1rem 0;color:inherit;background-color:currentColor;border:0;opacity:.25}
        @media (min-width: 768px) { .col-md-3{flex:0 0 auto;width:25%}.col-md-4{flex:0 0 auto;width:33.33333333%}.col-md-6{flex:0 0 auto;width:50%}.col-md-8{flex:0 0 auto;width:66.66666667%}.col-md-9{flex:0 0 auto;width:75%} }
        @media (min-width: 992px) { .col-lg-2{flex:0 0 auto;width:16.66666667%}.col-lg-10{flex:0 0 auto;width:83.33333333%} }
        .btn-close{box-sizing:content-box;width:1em;height:1em;padding:.25em .25em;color:#000;background:transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;border:0;border-radius:.25rem;opacity:.5}.btn-close:hover{color:#000;text-decoration:none;opacity:.75}.btn-close:focus{outline:0;box-shadow:0 0 0 .25rem rgba(13,110,253,.25);opacity:1}.btn-close:disabled,.btn-close.disabled{pointer-events:none;-webkit-user-select:none;-moz-user-select:none;user-select:none;opacity:.25}.alert .btn-close{position:absolute;top:0;right:0;z-index:2;padding:.9375rem 1.25rem}
    </style>
    
    <!-- Simple Icons (Offline) -->
    <style>
        /* Font Awesome Alternative - Simple Icons */
        .fas, .far, .fab { font-family: Arial, sans-serif; }
        .fa-user-circle::before { content: "👤"; }
        .fa-tachometer-alt::before { content: "📊"; }
        .fa-cash-register::before { content: "💰"; }
        .fa-users::before { content: "👥"; }
        .fa-box::before { content: "📦"; }
        .fa-tags::before { content: "🏷️"; }
        .fa-warehouse::before { content: "🏪"; }
        .fa-chart-line::before { content: "📈"; }
        .fa-receipt::before { content: "🧾"; }
        .fa-user::before { content: "👤"; }
        .fa-sign-out-alt::before { content: "🚪"; }
        .fa-clock::before { content: "🕐"; }
        .fa-check-circle::before { content: "✅"; }
        .fa-exclamation-circle::before { content: "⚠️"; }
        .fa-exclamation-triangle::before { content: "⚠️"; }
        .fa-barcode::before { content: "📊"; }
        .fa-search::before { content: "🔍"; }
        .fa-shopping-cart::before { content: "🛒"; }
        .fa-list::before { content: "📋"; }
        .fa-trash::before { content: "🗑️"; }
        .fa-edit::before { content: "✏️"; }
        .fa-plus::before { content: "➕"; }
        .fa-save::before { content: "💾"; }
        .fa-times::before { content: "❌"; }
        .fa-arrow-left::before { content: "←"; }
        .fa-home::before { content: "🏠"; }
        .fa-print::before { content: "🖨️"; }
        .fa-dollar-sign::before { content: "$"; }
        .fa-star::before { content: "⭐"; }
        .fa-bolt::before { content: "⚡"; }
        .fa-crown::before { content: "👑"; }
        .fa-check::before { content: "✓"; }
        .fa-info-circle::before { content: "ℹ️"; }
        .fa-lightbulb::before { content: "💡"; }
        .fa-magic::before { content: "✨"; }
        .fa-shield-alt::before { content: "🛡️"; }
        .fa-chart-pie::before { content: "📊"; }
        .fa-chart-area::before { content: "📈"; }
        .fa-chart-bar::before { content: "📊"; }
        .fa-store::before { content: "🏪"; }
        .fa-lock::before { content: "🔒"; }
        .fa-cog::before,
        .fa-gear::before { content: "⚙️"; }  /* gear icon */
        .fa-credit-card::before { content: "💳"; }
        .fas, .far, .fab { margin-right: 0.5rem; }
        .fa-2x { font-size: 2em; }
        .fa-3x { font-size: 3em; }
    </style>
    
    <!-- Custom CSS -->
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            padding-top: 20px;
        }
        
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 2px 10px;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: #495057;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stats-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stats-card-info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .barcode-input {
            font-family: 'Courier New', monospace;
        }
        
        .low-stock {
            background-color: #fff3cd;
        }
        
        .out-of-stock {
            background-color: #f8d7da;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <img src="../assets/logo.jpg" width="80" alt="Pharmacy Logo" class="mb-2 rounded-circle border border-white">
                    <h5 style="color: white;">Pharmacy System</h5>
                    <small class="text-muted">Welcome, <?php echo sanitizeInput($_SESSION['full_name']); ?></small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    
                    <a class="nav-link" href="pos.php">
                        <i class="fas fa-cash-register"></i> Point of Sale
                    </a>
                    
                    <?php if (isAdmin()): ?>
                    <hr class="text-muted">
                    <small class="text-muted px-3">ADMIN ONLY</small>
                    
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> User Management
                    </a>
                    
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i> Products
                    </a>
                    
                    <a class="nav-link" href="categories.php">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                    
                    <a class="nav-link" href="inventory.php">
                        <i class="fas fa-warehouse"></i> Inventory
                    </a>
                    
                    <a class="nav-link" href="sales_report.php">
                        <i class="fas fa-chart-line"></i> Sales Reports
                    </a>
                    <?php endif; ?>
                    
                    <hr class="text-muted">
                    
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-receipt"></i> Orders
                    </a>
                    
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>

                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Top Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0"><?php echo ucfirst(basename($_SERVER['PHP_SELF'], '.php')); ?></h2>
                    <div class="d-flex align-items-center">
                        <span class="me-3">
                            <i class="fas fa-user-circle"></i> <?php echo sanitizeInput($_SESSION['full_name']); ?>
                            <small class="text-muted">(<?php echo ucfirst($_SESSION['role']); ?>)</small>
                        </span>
                        <span class="text-muted">
                            <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
<?php endif; ?>