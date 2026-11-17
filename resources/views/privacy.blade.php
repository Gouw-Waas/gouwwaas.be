@extends('layouts.app')

@section('title', 'Privacyverklaring')
@section('description', 'Welke gegevens Gouw Waas via deze website verwerkt, waarom, met wie we ze delen en welke rechten je hebt.')

@php($updated = '17 november 2026')

@section('content')
    <section class="page-header">
        <div class="container">
            <h1>Privacyverklaring</h1>
            <p class="lead">Hoe we omgaan met de gegevens die je ons via deze website geeft.</p>
            <p class="muted small">Laatst aangepast op {{ $updated }}</p>
        </div>
    </section>

    <section class="container section">
        <div class="privacy-layout">
            <nav class="privacy-toc" aria-label="Inhoud">
                <p class="privacy-toc-title">Inhoud</p>
                <ol>
                    <li><a href="#wie-zijn-we">Wie zijn we?</a></li>
                    <li><a href="#welke-gegevens">Welke gegevens?</a></li>
                    <li><a href="#waarom">Waarom en op welke grond?</a></li>
                    <li><a href="#delen">Met wie delen we ze?</a></li>
                    <li><a href="#cookies">Cookies</a></li>
                    <li><a href="#bewaren">Hoe lang bewaren we ze?</a></li>
                    <li><a href="#beveiliging">Beveiliging</a></li>
                    <li><a href="#rechten">Jouw rechten</a></li>
                    <li><a href="#wijzigingen">Wijzigingen</a></li>
                </ol>
            </nav>

            <div class="privacy-content prose">
                <div class="privacy-summary card">
                    <h2 class="privacy-summary-title">In het kort</h2>
                    <ul>
                        <li>We vragen alleen wat nodig is om een activiteit te organiseren.</li>
                        <li>We verkopen niets en delen gegevens alleen met wie ze nodig heeft, zoals Mollie voor betalingen.</li>
                        <li>Geen advertenties, geen tracking en geen statistiekendiensten: enkel de cookies die de website nodig heeft.</li>
                        <li>Je kan altijd vragen wat we van je bijhouden, en het laten aanpassen of verwijderen.</li>
                    </ul>
                </div>

                <h2 id="wie-zijn-we">Wie zijn we?</h2>
                <p>
                    Deze website is van <strong>Gouw Waas</strong>, een gouw van Scouts en Gidsen Vlaanderen vzw. Gouw Waas is
                    verantwoordelijk voor de gegevens die via deze website verwerkt worden.
                </p>
                <address class="privacy-address">
                    Gouw Waas · Gouwhuis Karree<br>
                    Apostelstraat 5-7, 9100 Sint-Niklaas<br>
                    <a href="mailto:gouwbureau@gouwwaas.be">gouwbureau@gouwwaas.be</a>
                </address>
                <p>
                    Je ledenadministratie (als lid of leiding van een scoutsgroep) loopt <strong>niet</strong> via deze website,
                    maar via Scouts en Gidsen Vlaanderen. Daarover lees je meer op
                    <a href="https://www.scoutsengidsenvlaanderen.be/privacy" rel="noopener">scoutsengidsenvlaanderen.be/privacy</a>.
                    Scouts en Gidsen Vlaanderen heeft een functionaris voor gegevensbescherming, bereikbaar via
                    <a href="mailto:privacy@scoutsengidsenvlaanderen.be">privacy@scoutsengidsenvlaanderen.be</a> of per brief
                    (t.a.v. Functionaris voor Gegevensbescherming, Wilrijkstraat 45, 2140 Antwerpen).
                </p>

                <h2 id="welke-gegevens">Welke gegevens verwerken we?</h2>

                <h3>Als je je inschrijft voor een activiteit</h3>
                <ul>
                    <li>Je <strong>naam</strong> en <strong>e-mailadres</strong>.</li>
                    <li>Je <strong>antwoorden</strong> op de vragen die de organisator bij die activiteit stelt, bijvoorbeeld je groep, tak of dieetwensen.</li>
                    <li>Voor betalende activiteiten: het <strong>bedrag</strong> en de <strong>status van je betaling</strong> (betaald, mislukt, terugbetaald).</li>
                </ul>
                <p>
                    Je bankrekening- of kaartgegevens geef je rechtstreeks aan onze betaalpartner Mollie. Die gegevens komen
                    nooit bij ons terecht.
                </p>

                <h3>Als je een functie hebt in de gouw</h3>
                <p>
                    Op <a href="{{ route('wie-is-wie') }}">wie is wie</a> tonen we de mensen achter elke functie: je naam, eventueel je
                    totem, functietitel en foto, en het contactadres van de functie. Die pagina is openbaar. We plaatsen je er
                    alleen op met jouw akkoord, en halen je eraf wanneer je de functie niet meer hebt of als je dat vraagt.
                </p>

                <h3>Als je een account hebt</h3>
                <p>
                    Medewerkers die activiteiten beheren hebben een account met hun naam, e-mailadres en een wachtwoord. Dat
                    wachtwoord bewaren we alleen in versleutelde vorm (gehasht): niemand kan het lezen, ook wij niet.
                </p>

                <h3>Als je de website bezoekt</h3>
                <p>
                    Om de website te bezoeken hoef je niets in te vullen. We gebruiken alleen de cookies die de website nodig heeft
                    (zie <a href="#cookies">cookies</a>) en houden foutmeldingen bij om problemen op te lossen.
                </p>

                <h2 id="waarom">Waarom en op welke grond?</h2>
                <div class="table-scroll">
                    <table class="data-table privacy-table">
                        <thead>
                            <tr>
                                <th scope="col">Waarvoor</th>
                                <th scope="col">Grond (AVG)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Je inschrijving verwerken, de activiteit organiseren en je bevestigings- of annuleringsmail sturen</td>
                                <td>Uitvoering van de overeenkomst: je schrijft je in voor een activiteit</td>
                            </tr>
                            <tr>
                                <td>Je betaling verwerken, en terugbetalen als een inschrijving of activiteit geannuleerd wordt</td>
                                <td>Uitvoering van de overeenkomst</td>
                            </tr>
                            <tr>
                                <td>Gegevens over gezondheid of dieet, als een organisator daar uitdrukkelijk naar vraagt (bv. allergieën)</td>
                                <td>Jouw uitdrukkelijke toestemming, enkel voor die activiteit</td>
                            </tr>
                            <tr>
                                <td>Je naam, totem en foto op wie is wie</td>
                                <td>Jouw toestemming</td>
                            </tr>
                            <tr>
                                <td>Accounts voor medewerkers, de website beveiligen en fouten opsporen</td>
                                <td>Gerechtvaardigd belang: een veilige en werkende website</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p>We gebruiken je gegevens niet voor reclame en sturen je geen nieuwsbrieven via deze website.</p>

                <h2 id="delen">Met wie delen we je gegevens?</h2>
                <ul>
                    <li><strong>De organisatoren</strong> van een activiteit en de beheerders van de website zien de inschrijvingen voor die activiteit.</li>
                    <li><strong>Mollie</strong> verwerkt de online betalingen en terugbetalingen. Mollie volgt een eigen <a href="https://www.mollie.com/be/privacy" rel="noopener">privacybeleid</a>.</li>
                    <li><strong>Onze webhost</strong> bewaart de website en haar gegevens op servers in de Europese Unie en verstuurt onze e-mails.</li>
                    <li>
                        <strong>De kaart</strong> op <a href="{{ route('districten') }}">districten</a> laadt kaartbeelden van Digitaal Vlaanderen. Je
                        browser vraagt die daar rechtstreeks op, waardoor zij je IP-adres zien.
                    </li>
                    <li>
                        Links naar <strong>Google Maps</strong> (route plannen), Instagram of de websites van groepen brengen je naar die diensten,
                        pas als je erop klikt.
                    </li>
                </ul>
                <p>We verkopen je gegevens nooit en geven ze niet door aan anderen.</p>

                <h2 id="cookies">Cookies</h2>
                <p>We gebruiken alleen twee cookies, allebei nodig om de website te laten werken. Daarvoor is geen toestemming nodig.</p>
                <dl class="details privacy-cookies">
                    <div>
                        <dt>gouw-waas-session</dt>
                        <dd>Onthoudt je sessie, bijvoorbeeld dat je ingelogd bent. Verdwijnt na twee uur zonder activiteit.</dd>
                    </div>
                    <div>
                        <dt>XSRF-TOKEN</dt>
                        <dd>Beveiligt formulieren, zodat niemand anders iets namens jou kan versturen.</dd>
                    </div>
                </dl>
                <p>We gebruiken geen cookies van advertentie- of statistiekendiensten. Onze lettertypes staan op onze eigen server.</p>

                <h2 id="bewaren">Hoe lang bewaren we je gegevens?</h2>
                <ul>
                    <li><strong>Inschrijvingen</strong>: tot de activiteit gearchiveerd wordt. Dat gebeurt automatisch {{ \App\Models\Activity::retentionLabel() }} na afloop, of eerder door een beheerder. Daarbij worden alle inschrijvingen definitief verwijderd. Wordt je inschrijving geannuleerd, dan verdwijnt ze meteen.</li>
                    <li><strong>Betalingen</strong>: Mollie bewaart de gegevens van betalingen en terugbetalingen zo lang als de wet voor de boekhouding vereist.</li>
                    <li><strong>Wie is wie</strong>: zolang je de functie hebt.</li>
                    <li><strong>Accounts</strong>: zolang je toegang nodig hebt.</li>
                    <li><strong>Foutmeldingen</strong>: 14 dagen.</li>
                </ul>

                <h2 id="beveiliging">Beveiliging</h2>
                <p>
                    De website werkt alleen via een beveiligde verbinding (https). Wachtwoorden zijn gehasht, alleen organisatoren en
                    beheerders kunnen inschrijvingen bekijken, en betaalgegevens blijven bij Mollie.
                </p>

                <h2 id="rechten">Jouw rechten</h2>
                <p>Je hebt altijd het recht om:</p>
                <ul>
                    <li>te vragen welke gegevens we van je hebben, en een kopie te krijgen;</li>
                    <li>onjuiste gegevens te laten verbeteren of aanvullen;</li>
                    <li>je gegevens te laten verwijderen, als we ze niet meer nodig hebben of je je toestemming intrekt;</li>
                    <li>bezwaar te maken tegen een verwerking op basis van ons gerechtvaardigd belang;</li>
                    <li>een toestemming die je gaf op elk moment weer in te trekken.</li>
                </ul>
                <p>
                    Mail daarvoor naar <a href="mailto:gouwbureau@gouwwaas.be">gouwbureau@gouwwaas.be</a> en vermeld welk recht je wil
                    gebruiken. We antwoorden binnen de maand. Soms vragen we je eerst te bewijzen wie je bent, zodat niemand anders jouw
                    gegevens kan opvragen.
                </p>
                <p>
                    Ben je niet tevreden met ons antwoord? Dan kan je terecht bij de
                    <a href="https://www.gegevensbeschermingsautoriteit.be" rel="noopener">Gegevensbeschermingsautoriteit</a>.
                </p>

                <h2 id="wijzigingen">Wijzigingen</h2>
                <p>
                    We passen deze privacyverklaring aan als de website verandert. De meest recente versie staat altijd op deze pagina.
                    Laatst aangepast op {{ $updated }}.
                </p>
            </div>
        </div>
    </section>
@endsection
