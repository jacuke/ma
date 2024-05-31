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
    $.get('/umsteiger-suche-api?t=' + type + '&y=' + year + '&s=' + code, function(data) {
        $('#edit-modal .modal-content').html(data);
        $('#edit-modal .modal-title').html('Umsteiger-Historie');
    }).done(
        function(){initTooltips()}
    );
}

window.ajaxUmsteigerIcons = function(year, prev) {
    $.get('/umsteiger-icons?y=' + year + '&p=' + prev, function(data) {
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
    initTooltips();
});

const modalElement = document.getElementById('edit-modal');
if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', () => {
        loadModalDefaultContent();
    })
}

function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl)
    });
}

window.externalCodeLink = function(type, year, code) {

    const iyear = Number(year);

    let bfarmTab = window.open("about:blank",'external_code_link');

    if( iyear <= 2008 || (type === 'ops' && iyear === 2009)) {
        $.get('/dimdi-findcode?t=' + type + '&y=' + year + '&c=' + code, function(final_url) {
            bfarmTab.open(final_url,'external_code_link');
        });
    } else {

        let short_code = '';
        if( type === 'icd10gm') {
            short_code = code.substring(0, 3);
        }
        if( type === 'ops') {
            short_code = code.substring(0, 4);
        }

        let codesearch_url = '';
        let final_url = '';
        if( type === 'icd10gm') {
            if( iyear >= 2015 ) {
                codesearch_url = 'https://klassifikationen.bfarm.de/icd-10-gm/kode-suche/htmlgm' +
                    year + '/codesearch.js';
                final_url = 'https://klassifikationen.bfarm.de/icd-10-gm/kode-suche/htmlgm';
            } else {
                codesearch_url = 'https://www.dimdi.de/static/de/klassifikationen/icd/icd-10-gm/kode-suche/htmlgm' +
                    year +'/codesearch.js';
                final_url = 'https://www.dimdi.de/static/de/klassifikationen/icd/icd-10-gm/kode-suche/htmlgm';
            }
        }
        else if( type === 'ops') {
            if( iyear >= 2015 ) {
                codesearch_url = 'https://klassifikationen.bfarm.de/ops/kode-suche/htmlops' +
                    year + '/codesearch.js';
                final_url = 'https://klassifikationen.bfarm.de/ops/kode-suche/htmlops';
            } else {
                codesearch_url = 'https://www.dimdi.de/static/de/klassifikationen/ops/kode-suche/opshtml' +
                    year +'/codesearch.js';
                final_url = 'https://www.dimdi.de/static/de/klassifikationen/ops/kode-suche/opshtml';
            }
        }

        $.getScript( codesearch_url, function() {

            let htm_block = '';
            if(typeof classiCodeArray !== 'undefined') {
                classiCodeSetArray();
                htm_block = classiCodeArray[short_code];
            }

            if( type === 'icd10gm') {
                final_url += year + '/' + htm_block + '#' + short_code;
            }
            else if( type === 'ops') {
                final_url += year + '/' + htm_block + '#code' + code;
            }

            bfarmTab.open(final_url,'external_code_link');
        });
    }
}