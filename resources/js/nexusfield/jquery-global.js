/**
 * Pré-carrega o jQuery como global: Toastr e Summernote são UMD e procuram por
 * `window.jQuery` no momento em que são avaliados.
 */
import jQuery from 'jquery';

window.jQuery = jQuery;
window.$ = jQuery;
