/*
 * Entrada JavaScript do NEXUS-FIELD.
 *
 * jQuery entra antes de Toastr e Summernote porque os dois são UMD e procuram
 * `window.jQuery` no instante em que são avaliados. AdminLTE 4 cuida da
 * estrutura (sidebar, treeview, card) e Bootstrap 5 dos componentes; nossa
 * camada `Nf` expõe tema, notificação e diálogo — os únicos caminhos permitidos
 * para avisar e confirmar na tela.
 */
import './nexusfield/jquery-global';
import 'bootstrap';
import 'admin-lte';

import * as theme from './nexusfield/theme';
import { toast } from './nexusfield/notify';
import { confirmDialog, infoDialog, promptDialog } from './nexusfield/dialog';
import * as forms from './nexusfield/forms';
import * as flash from './nexusfield/flash';
import * as passwords from './nexusfield/passwords';
import * as confirmacao from './nexusfield/confirm';
import * as listas from './nexusfield/listas';

window.Nf = {
    theme,
    toast,
    confirm: confirmDialog,
    prompt: promptDialog,
    alert: infoDialog,
};

theme.init();
forms.init();
flash.init();
passwords.init();
confirmacao.init();
listas.init();
