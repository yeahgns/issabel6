<?php
/*
 * Tema Bradial Voz: rótulos dos módulos antigos do Issabel 4 no Issabel 5.
 *
 * Módulos como Construtor de módulo (build_module) e Remover módulo (delete_module) leem
 * os textos de $arrLangModule["..."], que o Issabel 5 deixou de preencher; por isso as
 * telas apareciam sem nenhum rótulo. O install.sh do tema insere uma linha nesses módulos
 * trocando $arrLangModule por este objeto, que devolve o texto do array original (quando
 * houver) ou a tradução do Issabel via _tr().
 */
if (!class_exists('BvLangShim')) {
    class BvLangShim implements ArrayAccess
    {
        private $base;

        public function __construct($base)
        {
            $this->base = is_array($base) ? $base : array();
        }

        #[\ReturnTypeWillChange]
        public function offsetExists($k)
        {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function offsetGet($k)
        {
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
