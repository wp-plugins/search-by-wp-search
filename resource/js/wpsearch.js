function isMobile() {
    var uagent = navigator.userAgent.toLowerCase();
    var mobile = (/iphone|ipad|ipod|android|blackberry|mini|windows\sce|palm/i.test(uagent));
    return mobile;
}
function showAllfactesClick(){
        var alias = jQuery(this).attr("data-alias"); 
            var status = jQuery(this).attr("data-status"); 
            c = jQuery('#div_facets_'+alias+'');
            if(status == "hide"){           
                if (isMobile()) {
                    c.css('height', 'auto');
                } else {
                    c.css('width',c.width());
                    c.css('height', c.height() + 100);
                    c.css('overflow-x','hidden');
                    c.css('overflow','auto'); 
                }
                jQuery(this).attr("data-status","show"); 
                jQuery('#facets_'+alias+'').children('li.hidden_facets').show();
               jQuery('#show_all_facets_'+alias+' span').text('Show less');
                
            }else{
                if (isMobile()) {
                    c.css('height', 'auto');
                } else {
                    c.css('width',c.width());
                    c.css('height', c.height() -100);
                    c.css('overflow-x','visible');
                    c.css('overflow','visible'); 
                }
                jQuery(this).attr("data-status","hide"); 
                jQuery('#facets_'+alias+'').children('li.hidden_facets').hide();
                jQuery('#show_all_facets_'+alias+' span').text('Show more');
            }
            /*if (isMobile()) {
                c.css('height', 'auto');
            } else {
                c.css('width',c.width()+10);
                c.css('height', c.height() + 200);
                c.css('overflow-x','hidden');
                c.css('overflow','auto'); 
            }
            jQuery('#facets_'+alias+'').children('li').show();*/
            return false;
    }
    
jQuery(document).ready(function( $ ) {
    $('a.show_all_facets').bind('click', showAllfactesClick);
});