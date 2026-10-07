<?php
if (!defined('ABSPATH')) exit;

/**
 * Bazowa klasa generatora treści. Nowy typ usługi = nowa klasa dziedzicząca po tej.
 */
abstract class CO_Generator {
    /** Unikalny identyfikator (a-z, 0-9, _). */
    abstract public function id();

    /** Nazwa widoczna w panelu produktu i zlecenia. */
    abstract public function label();

    /**
     * Pola formularza dla klienta: klucz => [label, type(text|textarea|select), options?, required?].
     */
    abstract public function fields();

    /** Czy generator potrafi sam wytworzyć wynik (przycisk "Generuj")? */
    public function is_automatic() {
        return false;
    }

    /** Czy po wygenerowaniu wynik ma iść do klienta bez ręcznego zatwierdzenia? */
    public function auto_deliver() {
        return false;
    }

    /** Czy generator obsługuje podgląd na stronie produktu? */
    public function has_preview() {
        return false;
    }

    /** Podgląd (binarnie JPEG) na podstawie wpisanych danych. */
    public function preview(array $input) {
        return '';
    }

    /** Dodatkowe czyszczenie pojedynczego pola po standardowej sanitacji. */
    public function clean($key, $val) {
        return $val;
    }

    /** Wynik: tekst, albo ['text' => ..., 'file' => ['name' => ..., 'data' => binarnie]], albo WP_Error. Wynik trafia do szkicu, a człowiek zatwierdza go przed wysyłką. */
    public function generate(array $input, $job_id) {
        return new WP_Error('co_manual', 'Ten generator działa ręcznie. Wgraj plik lub wpisz wynik.');
    }
}
