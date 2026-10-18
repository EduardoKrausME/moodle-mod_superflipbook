<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Super Flipbook language strings.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ackconfirm'] = 'Li e compreendi a declaração acima.';
$string['ackcount'] = 'Aceites registrados';
$string['acknowledge'] = 'Confirmar ciência';
$string['acknowledgement'] = 'Termo de ciência';
$string['ackreceipt'] = 'Ciência registrada: {$a}';
$string['ackreceipttext'] = 'Seu aceite para {$a} foi registrado.';
$string['ackstatement'] = 'Declaração de ciência';
$string['activetime'] = 'Tempo ativo (segundos)';
$string['allgroups'] = 'Todos os grupos';
$string['allowdownload'] = 'Permitir download pelos estudantes';
$string['allowprint'] = 'Permitir impressão';
$string['audittrail'] = 'Trilha de auditoria dos aceites';
$string['bookmark'] = 'Adicionar marcador';
$string['bookmarkprompt'] = 'Digite uma anotação privada para esta página:';
$string['bookmarks'] = 'Marcadores';
$string['canvaserror'] = 'O navegador não conseguiu criar uma área de desenho.';
$string['close'] = 'Fechar leitor';
$string['completiondetails'] = 'Concluir os requisitos de leitura';
$string['completionreading'] = 'Cumprir os requisitos de leitura';
$string['doublepage'] = 'Exibir duas páginas';
$string['download'] = 'Baixar PDF';
$string['dropoff'] = 'Abandono';
$string['eventacknowledged'] = 'Aceite de documento registrado';
$string['exitfullscreen'] = 'Sair da tela cheia';
$string['exportcsv'] = 'Exportar CSV';
$string['firstpage'] = 'Primeira página';
$string['fromdate'] = 'Data inicial';
$string['fullscreen'] = 'Tela cheia';
$string['fullscreenenterunsupported'] = 'O navegador não suporta a API de tela cheia.';
$string['fullscreenerror'] = 'Falha ao alternar tela cheia: {$a}. Se estiver em iframe, habilite allowfullscreen no elemento iframe.';
$string['fullscreenexitunsupported'] = 'O navegador não suporta sair da tela cheia.';
$string['gallery'] = 'Biblioteca de PDFs';
$string['gallerycount'] = '{$a} documentos';
$string['grade'] = 'Nota';
$string['ipaddress'] = 'Endereço IP';
$string['lastpage'] = 'Última página';
$string['lastview'] = 'Última visualização';
$string['learners'] = 'Estudantes';
$string['loading'] = 'Carregando documento...';
$string['maxpage'] = 'Última página alcançada';
$string["mcp_banner_badge"] = "INTEGRAÇÃO COM IA";
$string["mcp_banner_benefit_control"] = "Confirmação obrigatória para alterações";
$string["mcp_banner_benefit_discovery"] = "Descoberta automática de ferramentas";
$string["mcp_banner_benefit_security"] = "Permissões do Moodle respeitadas";
$string["mcp_banner_cta"] = "Conhecer o Moodle MCP";
$string["mcp_banner_description"] = "Instale o local_mcp para disponibilizar os recursos do Super Flipbook ao ChatGPT, respeitando as permissões do Moodle.";
$string["mcp_banner_dismiss"] = "Não mostrar novamente";
$string["mcp_banner_title"] = "Use o Super Flipbook com o ChatGPT";
$string["mcpadd_description"] = "Acrescenta um PDF em Base64 a uma atividade Super Flipbook existente, sem substituir documentos ou reiniciar o progresso de leitura do primeiro arquivo. Consulte superflipbook_list_pdfs e informe o hash de estado. Exige confirmação.";
$string["mcpadd_title"] = "Super Flipbook: adicionar PDF";
$string["mcpcreate_description"] = "Cria uma atividade Super Flipbook com os bytes Base64 de um PDF gerado no ChatGPT, armazenado pela API de arquivos do Moodle. Exige confirmação.";
$string["mcpcreate_title"] = "Super Flipbook: criar atividade com PDF";
$string["mcpfirsttracked"] = "Somente o primeiro PDF participa do acompanhamento de leitura e conclusão.";
$string["mcplist_description"] = "Lista metadados dos PDFs, o primeiro documento acompanhado e o hash de estado necessário para adicionar arquivos. Não retorna o conteúdo dos PDFs.";
$string["mcplist_title"] = "Super Flipbook: listar PDFs";
$string["mcppdfbusy"] = "Há outro envio de PDF em andamento. Tente novamente.";
$string["mcppdfduplicate"] = "Já existe um PDF com esse nome nesta atividade.";
$string["mcppdfempty"] = "Esta atividade não contém PDF. Use o editor do Moodle para corrigir a atividade.";
$string["mcppdfname"] = "Informe um nome de arquivo seguro terminado em .pdf.";
$string["mcppdfstale"] = "A biblioteca de PDFs foi modificada após a consulta. Liste os documentos novamente antes de enviar.";
$string['messageprovider:acknowledged'] = 'Notificações de confirmação de leitura';
$string['minpercentage'] = 'Percentual mínimo de páginas visitadas';
$string['minseconds'] = 'Tempo mínimo de leitura ativa (segundos)';
$string['missingpdf'] = 'O arquivo PDF da atividade não foi encontrado.';
$string['modulename'] = 'Super Flipbook';
$string['modulename_help'] = 'Leia um PDF como livro interativo, com acompanhamento por página e termo de ciência opcional.';
$string['modulename_link'] = 'mod/superflipbook/view';
$string['modulenameplural'] = 'Super Flipbooks';
$string['navigatepages'] = 'Navegar pelas páginas';
$string['nextpage'] = 'Próxima página';
$string['openingpdf'] = 'Abrindo arquivo PDF...';
$string['operationdenied'] = 'Operação não permitida.';
$string['page'] = 'Página';
$string['pageanalytics'] = 'Análise por página';
$string['pageinputlabel'] = 'Número da página';
$string['pagenumber'] = 'Página {$a}';
$string['pageof'] = 'de';
$string['pagerendererror'] = 'Erro ao renderizar a página: {$a}';
$string['pages'] = 'PÁGINAS';
$string['pdfcontent'] = 'Documento PDF';
$string['pdfcountinvalid'] = 'Envie entre 1 e 30 documentos PDF.';
$string['pdfdetailslabel'] = 'ARQUIVO PDF';
$string['pdfinvalid'] = 'Este arquivo não parece ser um PDF válido.';
$string['pdfjsloaderror'] = 'Não foi possível carregar o PDF.js. Verifique sua conexão com a internet.';
$string['pdfloaderror'] = 'Erro ao carregar PDF: {$a}. Verifique o endereço, as permissões de acesso e o CORS, se o PDF estiver em outro domínio.';
$string['pdfopenfailed'] = 'NÃO FOI POSSÍVEL ABRIR O DOCUMENTO';
$string['pdfpagealt'] = 'Página {$a} do documento';
$string['pdfpasswordprompt'] = 'Este PDF é protegido. Digite a senha para abri-lo:';
$string['pdfplaceholder'] = 'PDF';
$string['pdftitle'] = 'Leitor de PDF';
$string['pdfurlerror'] = 'Use uma URL HTTP ou HTTPS para o arquivo PDF.';
$string['percentageerror'] = 'Informe um número entre 0 e 100.';
$string['pluginadministration'] = 'Administração do Super Flipbook';
$string['pluginname'] = 'Super Flipbook';
$string['positiveerror'] = 'Informe um número positivo.';
$string['preparingpages'] = 'Preparando as páginas...';
$string['previouspage'] = 'Página anterior';
$string['print'] = 'Imprimir';
$string['privacy:acknowledgement'] = 'A declaração de ciência aceita.';
$string['privacy:activity'] = 'A atividade associada.';
$string['privacy:date'] = 'A data e hora da ação.';
$string['privacy:ip'] = 'O endereço IP opcional usado ao registrar um aceite.';
$string['privacy:notes'] = 'Anotações e marcadores pessoais.';
$string['privacy:pageviews'] = 'Métricas de leitura por página.';
$string['privacy:progress'] = 'Progresso de leitura e conclusão.';
$string['privacy:reading'] = 'Páginas visualizadas, tempo de sessão e conclusão.';
$string['privacy:userid'] = 'O usuário relacionado ao registro.';
$string['privacysection'] = 'Controle de acesso e responsabilidade';
$string['readers'] = 'Leitores';
$string['readinghonesty'] = 'O tempo de leitura no navegador é uma estimativa. A marca d’água não impede capturas de tela nem cópias externas.';
$string['readingprogress'] = 'Páginas visualizadas';
$string['readingrules'] = 'Requisitos de leitura';
$string['recordip'] = 'Armazenar IP dos aceites';
$string['report'] = 'Relatório de leitura';
$string['requireack'] = 'Exigir aceite explícito';
$string['requiredpages'] = 'Números das páginas obrigatórias';
$string['requiredpages_help'] = 'Números de páginas separados por vírgulas, por exemplo: 1,3,5.';
$string['requiredpageserror'] = 'Use apenas números de páginas separados por vírgulas.';
$string['requirelastpage'] = 'Exigir última página';
$string['resetuserdata'] = 'Excluir registros de leitura do Super Flipbook';
$string['search'] = 'Pesquisar';
$string['searchnotfound'] = 'Texto não encontrado';
$string['searchplaceholder'] = 'Buscar texto no PDF';
$string['singlepage'] = 'Exibir uma página';
$string['superflipbook:addinstance'] = 'Adicionar atividades Super Flipbook';
$string['superflipbook:download'] = 'Baixar PDFs do Super Flipbook';
$string['superflipbook:manage'] = 'Gerenciar atividades Super Flipbook';
$string['superflipbook:view'] = 'Visualizar atividades Super Flipbook';
$string['superflipbook:viewreports'] = 'Visualizar relatórios do Super Flipbook';
$string['thumbcreateerror'] = 'Não foi possível gerar a miniatura do PDF.';
$string['thumbnails'] = 'Miniaturas';
$string['thumbuploaderror'] = 'O Moodle não aceitou a miniatura do PDF.';
$string['todate'] = 'Data final';
$string['toolbar'] = 'Barra de ferramentas do leitor';
$string['totalpages'] = 'Total de páginas';
$string['unknownerror'] = 'Erro desconhecido.';
$string['views'] = 'Visualizações';
$string['waitingpdf'] = 'AGUARDANDO PDF';
$string['watermark'] = 'Exibir marca d’água com identificação';
$string['zoomin'] = 'Ampliar zoom';
$string['zoomout'] = 'Reduzir zoom';
