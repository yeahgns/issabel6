<?php
/*
 * Tema Bradial Voz: rótulos dos módulos antigos do Issabel 4 no Issabel 5.
 *
 * Módulos como Construtor de módulo (build_module) e Remover módulo (delete_module) leem
 * os textos de $arrLangModule["..."], que o Issabel 5 deixou de preencher; por isso as
 * telas apareciam sem nenhum rótulo. O install.sh do tema insere uma linha nesses módulos
 * trocando $arrLangModule por este objeto, que devolve o texto do array original (quando
 * houver) ou a tradução do Issabel via _tr().
 *
 * Em português (Sistema > Preferências > Idioma), usa a tabela PT abaixo antes de tudo,
 * porque esses módulos não trazem tradução para o português. "Yes" e "No" ficam de fora:
 * o JavaScript do Construtor de módulo usa esses textos como valor das opções.
 */
if (!class_exists('BvLangShim')) {
    class BvLangShim implements ArrayAccess
    {
        private $base;
        private $pt = false;

        private static $PT = array(
            // Construtor de módulo
            'Build Module' => 'Construtor de módulo', 'Save' => 'Salvar', 'Required field' => 'Campo obrigatório',
            'General Information' => 'Informações gerais', 'Location' => 'Localização',
            'Module Description' => 'Descrição do módulo', 'Your e-mail' => 'Seu e-mail',
            'Module Name' => 'Nome do módulo', 'Module Id' => 'ID do módulo', 'Your Name' => 'Seu nome',
            'Module Type' => 'Tipo de módulo', 'Grid' => 'Listagem', 'Form' => 'Formulário', 'Framed' => 'Página externa',
            'Field Name' => 'Nome do campo', 'Type Field' => 'Tipo do campo', 'Url' => 'URL',
            'Level 2' => 'Nível 2', 'Level 3' => 'Nível 3', 'Module Level' => 'Nível do módulo',
            'Level 1 Parent Exists' => 'O menu de nível 1 já existe', 'Level 2 Parent Exists' => 'O menu de nível 2 já existe',
            'Level 1 Parent' => 'Menu de nível 1', 'Level 2 Parent' => 'Menu de nível 2',
            'Level 1 Parent Name' => 'Nome do menu de nível 1', 'Level 1 Parent Id' => 'ID do menu de nível 1',
            'Level 2 Parent Name' => 'Nome do menu de nível 2', 'Level 2 Parent Id' => 'ID do menu de nível 2',
            'Group Permission' => 'Grupos com acesso',
            // Remover módulo
            'Delete Module' => 'Remover módulo', 'Delete' => 'Remover', 'Menu' => 'Menu',
            'Delete Menu' => 'Remover do menu', 'Delete Files' => 'Apagar os arquivos',
            'Are you sure you wish to continue?' => 'Tem certeza de que quer continuar?',
            'ERROR' => 'Erro', 'The module was deleted' => 'O módulo foi removido.',
            "The module couldn't be deleted" => 'Não foi possível remover o módulo.',
            "You haven't selected any option to delete: Menu or Files" => 'Escolha o que remover: o item do menu, os arquivos ou os dois.',
        );

        public function __construct($base)
        {
            $this->base = is_array($base) ? $base : array();
            $l = function_exists('get_language') ? strtolower((string) get_language()) : '';
            $this->pt = (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0);
        }

        #[\ReturnTypeWillChange]
        public function offsetExists($k)
        {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function offsetGet($k)
        {
            if ($this->pt && isset(self::$PT[$k])) return self::$PT[$k];
            if (isset($this->base[$k]) && $this->base[$k] !== '') return $this->base[$k];
            return function_exists('_tr') ? _tr($k) : $k;
        }

        #[\ReturnTypeWillChange]
        public function offsetSet($k, $v)
        {
            if ($k === null) $this->base[] = $v; else $this->base[$k] = $v;
        }

        #[\ReturnTypeWillChange]
        public function offsetUnset($k)
        {
            unset($this->base[$k]);
        }
    }
}
