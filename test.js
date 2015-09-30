jQuery(function(){

    var text_open = Drupal.t('Read more');
    var text_close = Drupal.t('Read less');
    jQuery('.view-page-sub-sections .view-content > div.skizo-collapsible').each(function(){
        var __this = jQuery(this);

        __this.find('.views-field-body, .views-field-body-et').hide();

        var collapsed = true;

        __this.find('.views-field-title, .views-field-title-field-et').append(generate_collapser());
        //__this.find('.mcollapsible-subpage').on('click', function(){
        __this.find('div.view-page-sub-sections div.views-row div.collapse-wrapper div.views-field-field-read-less, div.view-page-sub-sections div.views-row div.collapse-wrapper div.views-field-field-read').on('click', function(){
        /*
         __this.find('.views-field-body, .views-field-body-et').toggle(500);

         __this.find('.mcollapsible-subpage p').html(!collapsed ? text_open : text_close);
         __this.find('.mcollapsible-subpage i').switchClass(collapsed ? 'fa-angle-down' : 'fa-angle-up', collapsed ? 'fa-angle-up' : 'fa-angle-down');

         collapsed = !collapsed;
         */
        var __this = jQuery(this);
        if(__this.hasClass('views-field-field-read-less')){
            __this.parent().find('.views-field-field-read-more').show();
            __this.hide();
        }
        else{
            __this.parent().find('.views-field-field-read-less').show();
            __this.hide();
        }
        __this.parent().parent().parent().find('.views-field-body-et').toggle(500);
    });

});

jQuery('.view-page-sub-sections .view-content > div.skizo-non-collapsible').each(function() {
    var __this = jQuery(this);
    __this.find('.views-field-title').css('margin-bottom', 24);
    __this.find('.views-field-title').css('overflow', 'hidden');


});

function generate_collapser(){
    return '<div class="mcollapsible-subpage"><p>' + text_open +'</p><i class="fa fa-angle-down"></i></div>';
}
});

