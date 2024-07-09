/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

require('bootstrap');

import './styles/app.scss';
import $ from 'jquery';

import '@popperjs/core';
window.bootstrap = require('bootstrap/dist/js/bootstrap.bundle.js');

window.ajaxUmsteigerSearchHistory = function(type, year, code) {
    $.get('umsteiger-suche-api?t=' + type + '&y=' + year + '&s=' + code, function(data) {
        $('#edit-modal .modal-content').html(data);
        $('#edit-modal .modal-title').html('Umsteiger-Historie');
    });
}

window.ajaxUmsteigerIcons = function(year, prev) {
    $.get('umsteiger-icons?y=' + year + '&p=' + prev, function(data) {
        $('#edit-modal .modal-content').html(data);
        $('#edit-modal .modal-title').html('"Auto" Icons');
    });
}

function loadModalDefaultContent(){
    $('#edit-modal .modal-content').html(
        '<div class="m-3"><div class="d-flex justify-content-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div></div>'
    );
}

$(document).ready(function() {
    loadModalDefaultContent();
});

const modalElement = document.getElementById('edit-modal');
if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', () => {
        loadModalDefaultContent();
    })
}

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(tooltipTriggerEl => {
    new bootstrap.Tooltip(tooltipTriggerEl)
})