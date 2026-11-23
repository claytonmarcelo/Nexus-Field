/*
 * JavaScript das telas abertas de acesso: sem jQuery, Bootstrap, AdminLTE,
 * Toastr, Summernote ou Leaflet. Só o que login, cadastro e recuperação usam.
 */
import * as theme from './nexusfield/theme';
import * as forms from './nexusfield/forms';
import * as passwords from './nexusfield/passwords';
import * as sessao from './nexusfield/session';
import { init as initFlash } from './nexusfield/auth-flash';

theme.init();
forms.init();
passwords.init();
sessao.init();
initFlash();
