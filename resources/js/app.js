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
import * as editor from './nexusfield/editor';
import * as checkin from './nexusfield/checkin';

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
editor.init();
checkin.init();

/*
 * O calendário é o único peso da tela que nenhuma outra página usa. Ele entra sob
 * demanda, quando existe um quadro montado: o login, o painel e as listagens
 * continuam baixando o pacote de sempre, e só a agenda paga pelos plugins do
 * FullCalendar.
 */
if (document.querySelector('[data-nf-agenda]')) {
    import('./nexusfield/agenda').then((modulo) => modulo.init());
}
