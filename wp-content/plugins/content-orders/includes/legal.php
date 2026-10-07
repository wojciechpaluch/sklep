<?php
if (!defined('ABSPATH')) exit;

/**
 * Wzory regulaminu i polityki prywatności. To szablony do weryfikacji przez właściciela (lub prawnika),
 * nie porada prawna. Znaczniki {SPRZEDAWCA}, {ADRES}, {EMAIL}, {STRONA} są podmieniane danymi z konfiguratora.
 */
function co_legal_fill($html, array $d) {
    return strtr($html, [
        '{SPRZEDAWCA}' => esc_html($d['seller']),
        '{ADRES}' => esc_html($d['address']),
        '{EMAIL}' => esc_html($d['email']),
        '{STRONA}' => esc_html(home_url('/')),
        '{PLATNOSC}' => !empty($d['blik']) ? 'przelewem tradycyjnym lub BLIK-iem na telefon' : 'przelewem tradycyjnym',
    ]);
}

function co_legal_terms() {
    return <<<'HTML'
<h2>§1 Postanowienia ogólne</h2>
<p>1. Regulamin określa zasady zakupów w sklepie internetowym dostępnym pod adresem {STRONA} (dalej: „Sklep”).</p>
<p>2. Sprzedawcą jest {SPRZEDAWCA}, adres: {ADRES}, e-mail: {EMAIL} (dalej: „Sprzedawca”). Sprzedawca prowadzi działalność nierejestrowaną w rozumieniu art. 5 ustawy Prawo przedsiębiorców i nie jest płatnikiem podatku VAT.</p>
<p>3. Klientem może być osoba pełnoletnia oraz małoletnia w zakresie umów zwyczajowo zawieranych w drobnych sprawach życia codziennego.</p>

<h2>§2 Oferta</h2>
<p>1. Sklep oferuje treści cyfrowe oraz treści tworzone na zamówienie według danych podanych przez Klienta (m.in. dyplomy w formacie PDF, wierszyki, piosenki).</p>
<p>2. Ceny podane są w złotych polskich i są cenami ostatecznymi.</p>

<h2>§3 Zamówienie i płatność</h2>
<p>1. Zamówienie składa się przez formularz w Sklepie. Klient podaje dane niezbędne do realizacji (m.in. imię, nazwisko, adres e-mail oraz treści potrzebne do wykonania produktu) i odpowiada za ich poprawność.</p>
<p>2. Płatność następuje {PLATNOSC}, zgodnie z instrukcją wyświetlaną po złożeniu zamówienia. Realizacja rozpoczyna się po zaksięgowaniu płatności.</p>

<h2>§4 Realizacja i dostawa</h2>
<p>1. Produkt zostaje dostarczony w formie elektronicznej na adres e-mail podany w zamówieniu, w treści wiadomości lub przez link do pobrania.</p>
<p>2. Czas realizacji podany jest przy produkcie. Jeśli nie wskazano inaczej, wynosi do 48 godzin od zaksięgowania płatności.</p>

<h2>§5 Prawo odstąpienia od umowy</h2>
<p>1. Konsumentowi przysługuje co do zasady prawo odstąpienia od umowy w ciągu 14 dni bez podania przyczyny.</p>
<p>2. Prawo to nie przysługuje w odniesieniu do: (a) umów o dostarczanie treści cyfrowych niedostarczanych na nośniku materialnym, jeżeli spełnianie świadczenia rozpoczęło się za wyraźną zgodą Konsumenta przed upływem terminu odstąpienia, po poinformowaniu go o utracie prawa odstąpienia (art. 38 pkt 13 ustawy o prawach konsumenta); (b) umów, w których przedmiotem świadczenia jest rzecz nieprefabrykowana, wyprodukowana według specyfikacji Konsumenta (art. 38 pkt 3 ustawy o prawach konsumenta).</p>
<p>3. Przed złożeniem zamówienia Klient potwierdza w formularzu, że żąda rozpoczęcia realizacji od razu i przyjmuje do wiadomości utratę prawa odstąpienia.</p>

<h2>§6 Reklamacje</h2>
<p>1. Reklamacje można składać e-mailem na adres {EMAIL}, opisując problem i podając numer zamówienia.</p>
<p>2. Sprzedawca rozpatruje reklamację w terminie 14 dni. W przypadku niezgodności produktu z umową (np. błąd Sprzedawcy w imieniu lub treści) Sprzedawca poprawi produkt albo zwróci zapłaconą cenę.</p>
<p>3. Błędy wynikające z danych podanych przez Klienta nie są niezgodnością z umową. Sprzedawca zapewnia jedną bezpłatną poprawkę w ciągu 7 dni od dostawy.</p>
<p>4. Konsument może skorzystać z pozasądowych sposobów rozpatrywania reklamacji i dochodzenia roszczeń, w szczególności z mediacji lub sądu polubownego przy Wojewódzkim Inspektoracie Inspekcji Handlowej.</p>

<h2>§7 Treści generowane z użyciem AI i licencja</h2>
<p>1. Produkty mogą być tworzone z użyciem narzędzi sztucznej inteligencji. Sprzedawca sprawdza je przed dostarczeniem w zakresie opisanym w ofercie.</p>
<p>2. Produkty przeznaczone są do użytku osobistego (np. jako prezent). Publikacja w celach komercyjnych wymaga zgody Sprzedawcy.</p>
<p>3. Klient oświadcza, że ma prawo do przesłanego zdjęcia zwierzaka i że nie przedstawia ono osób ani treści niezgodnych z prawem. Zdjęcia są kasowane po 30 dniach.</p>
<p>4. Produkty o charakterze żartu nie naśladują oficjalnych pism ani symboli państwowych, urzędów, sądów, policji, banków ani innych instytucji i są oznaczone jako fikcyjne. Nie wolno ich używać do wprowadzania w błąd, zastraszania, nękania ani wyłudzeń.</p>
<p>5. Klient nie może zamawiać treści obraźliwych, wulgarnych, naruszających prawa lub dobra osobiste osób trzecich. Sprzedawca może odmówić realizacji i zwrócić zapłaconą cenę.</p>

<h2>§8 Dane osobowe</h2>
<p>Zasady przetwarzania danych osobowych opisuje Polityka prywatności dostępna w Sklepie.</p>

<h2>§9 Postanowienia końcowe</h2>
<p>1. W sprawach nieuregulowanych stosuje się prawo polskie.</p>
<p>2. Sprzedawca może zmienić Regulamin. Zmiany nie dotyczą zamówień złożonych przed ich wprowadzeniem.</p>
HTML;
}

function co_legal_privacy() {
    return <<<'HTML'
<h2>1. Administrator danych</h2>
<p>Administratorem danych osobowych jest {SPRZEDAWCA}, adres: {ADRES}, e-mail: {EMAIL}.</p>

<h2>2. Jakie dane przetwarzamy i po co</h2>
<p>Przetwarzamy dane podane w zamówieniu: imię, nazwisko, adres e-mail oraz treści potrzebne do wykonania produktu (np. imię osoby na dyplomie, opis do wierszyka lub piosenki).</p>
<ul>
<li>realizacja zamówienia i kontakt w sprawie zamówienia (art. 6 ust. 1 lit. b RODO),</li>
<li>wypełnianie obowiązków prawnych, np. podatkowych i księgowych (art. 6 ust. 1 lit. c RODO),</li>
<li>ustalenie, dochodzenie lub obrona roszczeń (art. 6 ust. 1 lit. f RODO),</li>
<li>wykonanie produktu ze zdjęciem zwierzaka: przesłane zdjęcie przechowujemy w zabezpieczonym katalogu do 30 dni, po czym je usuwamy (art. 6 ust. 1 lit. b RODO),</li>
<li>statystyka źródeł sprzedaży: zapisujemy w pliku cookie, z jakiego linku wszedł Klient (art. 6 ust. 1 lit. f RODO).</li>
</ul>

<h2>3. Dane osób trzecich</h2>
<p>Produkty są często robione dla kogoś innego (np. prezent lub żart). Zamawiający może podać wyłącznie imię lub pseudonim osoby, dla której przygotowuje produkt, i oświadcza, że robi to w dobrej wierze i nie narusza jej dóbr osobistych. Nie przyjmujemy zdjęć osób ani nazwisk, adresów i danych kontaktowych osób trzecich. Produkt trafia wyłącznie do zamawiającego, nigdy bezpośrednio do osoby, której dotyczy.</p>

<h2>3a. Odbiorcy danych</h2>
<p>Dane mogą być przekazywane: dostawcy hostingu i poczty e-mail, bankowi realizującemu płatność oraz dostawcy narzędzi AI, jeśli produkt jest wykonywany z ich użyciem (np. Anthropic, USA, na podstawie standardowych klauzul umownych). Do narzędzi AI przekazujemy wyłącznie treści potrzebne do wykonania produktu.</p>

<h2>4. Czas przechowywania</h2>
<p>Dane przechowujemy przez czas realizacji zamówienia, a następnie przez okres wymagany przepisami prawa (w tym podatkowymi) oraz do czasu przedawnienia roszczeń.</p>

<h2>5. Prawa osoby, której dane dotyczą</h2>
<p>Przysługuje Ci prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia oraz sprzeciwu, a także prawo wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych. Kontakt: {EMAIL}.</p>

<h2>6. Pliki cookie</h2>
<p>Sklep używa plików cookie niezbędnych do działania koszyka i zamówień oraz pliku cookie zapamiętującego źródło wejścia (do 30 dni). Nie używamy reklamowych plików cookie podmiotów trzecich.</p>
HTML;
}
