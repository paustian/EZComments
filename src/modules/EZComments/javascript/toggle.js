// Copyright Zikula Foundation 2010 - license GNU/LGPLv2.1 (or at your option, any later version).

function CheckAll(formtype) {
    $$('.' + formtype + '_checkbox').each(function(el) { el.checked = $('toggle_' + formtype).checked;});
}

function CheckCheckAll(formtype) {
    var totalon = 0;
    $$('.' + formtype + '_checkbox').each(function(el) { if (el.checked) { totalon++; } });
    $('toggle_' + formtype).checked = ($$('.' + formtype + '_checkbox').length==totalon);
}