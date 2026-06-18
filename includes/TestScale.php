<?php

namespace BalanceTesting;

defined( 'ABSPATH' ) || exit;

/**
 * Test difficulty scale definitions keyed by ACF field `test_scale`.
 */
class TestScale {
    use SingletonTrait;

    /**
     * @return array<string, array{slug: string, headers: array<int, string>, rows: array<int, array{level: int, name: string, description: string}>}>
     */
    public function get_scales(): array {
        return array(
            'balance' => array(
                'slug'     => 'balance',
                'headers'  => array(
                    __( 'Asteikko', 'balance-testing' ),
                    __( 'Nimi', 'balance-testing' ),
                    __( 'Kuvaus', 'balance-testing' ),
                ),
                'rows'     => array(
                    array(
                        'level'       => 1,
                        'name'        => __( 'Helppo', 'balance-testing' ),
                        'description' => __( 'Testi sujuu vaivattomasti ilman epävarmuutta ja vaatii vain vähän keskittymistä.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 2,
                        'name'        => __( 'Melko helppo / helpohko', 'balance-testing' ),
                        'description' => __( 'Testi vaatii enemmän keskittymistä, mutta sen suorittaminen menee melko helposti.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 3,
                        'name'        => __( 'Kohtalaisen haastava / menettelevä', 'balance-testing' ),
                        'description' => __( 'Testi vaatii jatkuvaa keskittymistä ja tuntuu ajoittain haastavalta ja horjuttavalta, mutta sen tekeminen onnistuu ilman tukea. Testin tekeminen väsyttää hieman.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 4,
                        'name'        => __( 'Vaikea', 'balance-testing' ),
                        'description' => __( 'Tasapainon säilyttäminen on haastavampaa ja horjuttaa ja tukea voi joutua käyttämään hetkellisesti. Testin tekeminen väsyttää selvästi.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 5,
                        'name'        => __( 'Hyvin vaikea', 'balance-testing' ),
                        'description' => __( 'Tasapainon ylläpitäminen on hyvin vaikeaa ja testin aikana on selviä horjahduksia. Tarvitset tukea suuren osan ajasta.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 6,
                        'name'        => __( 'Mahdoton', 'balance-testing' ),
                        'description' => __( 'Et pysty kunnolla tekemään testiä, koska se lähtee kaatamaan jo alussa ja on liian vaikea tehdä. Testin yrittäminen uudelleen tuntuu vaaralliselta.', 'balance-testing' ),
                    ),
                ),
            ),
            'eyes' => array(
                'slug'     => 'eyes',
                'headers'  => array(
                    __( 'Asteikko', 'balance-testing' ),
                    __( 'Nimi', 'balance-testing' ),
                    __( 'Kuvaus', 'balance-testing' ),
                ),
                'rows'     => array(
                    array(
                        'level'       => 1,
                        'name'        => __( 'Helppo', 'balance-testing' ),
                        'description' => __( 'Silmät pysyvät helposti paikallaan ja viisitoista kertaa pään liikuttaminen kumpaankin suuntaan ei väsytä.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 2,
                        'name'        => __( 'Melko helppo / helpohko', 'balance-testing' ),
                        'description' => __( 'Testin tekeminen vaatii keskittymistä, mutta sen suorittaminen ei väsytä.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 3,
                        'name'        => __( 'Kohtalaisen haastava / menettelevä', 'balance-testing' ),
                        'description' => __( 'Testi vaatii enemmän keskittymistä ja viidentoista kerran tekeminen väsyttää jonkun verran. Huomaat haastetta tasapainossa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 4,
                        'name'        => __( 'Vaikea', 'balance-testing' ),
                        'description' => __( 'Testit vaatii paljon keskittymistä. Silmät tuntuvat karkailevan jonkun verran samalla kun testin tekeminen on väsyttävää. Huomaat haastetta tasapainossa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 5,
                        'name'        => __( 'Hyvin vaikea', 'balance-testing' ),
                        'description' => __( 'Testin tekeminen on haastavaa ja väsyttää nopeasti muutamalla toistolla. Testin aikana tasapainon ylläpito on vaikea.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 6,
                        'name'        => __( 'Mahdoton', 'balance-testing' ),
                        'description' => __( 'Et pysty kunnolla tekemään testiä, koska silmien pitäminen rastissa tuntuu mahdottomalta. Testi väsyttää hyvin paljon jo ensimmäisissä toistoissa.', 'balance-testing' ),
                    ),
                ),
            ),
            'coordination' => array(
                'slug'     => 'coordination',
                'headers'  => array(
                    __( 'Asteikko', 'balance-testing' ),
                    __( 'Nimi', 'balance-testing' ),
                    __( 'Kuvaus', 'balance-testing' ),
                ),
                'rows'     => array(
                    array(
                        'level'       => 1,
                        'name'        => __( 'Helppo', 'balance-testing' ),
                        'description' => __( 'Testi sujuu vaivattomasti ja vaatii vain vähän keskittymistä. Testin tekeminen on sujuvaa ja tarkkaa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 2,
                        'name'        => __( 'Melko helppo / helpohko', 'balance-testing' ),
                        'description' => __( 'Testi vaatii enemmän keskittymistä, mutta sen suorittaminen menee melko helposti. Testin tekeminen on melko sujuvaa ja tarkkaa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 3,
                        'name'        => __( 'Kohtalaisen haastava / menettelevä', 'balance-testing' ),
                        'description' => __( 'Testi vaatii jatkuvaa keskittymistä ja tuntuu ajoittain haastavalta ja horjuttavalta. Testin tekemisen sujuvuus ja tarkkuus on kohtalaisen haastava. Testin tekeminen väsyttää hieman.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 4,
                        'name'        => __( 'Vaikea', 'balance-testing' ),
                        'description' => __( 'Tasapainon säilyttäminen on haastavampaa ja horjuttaa ja tukea voi joutua käyttämään hetkellisesti. Testin tekemisen sujuvuus ja tarkkuus on haastavaa. Testin tekeminen väsyttää selvästi. Tarvitset ajoittaista tukea testin tekemiseen.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 5,
                        'name'        => __( 'Hyvin vaikea', 'balance-testing' ),
                        'description' => __( 'Tasapainon ylläpitäminen on hyvin vaikeaa ja testin aikana on selviä horjahduksia. Testin tekemisen sujuvuus on hyvin vaikeaa. Tarvitset tukea testin tekemiseen.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 6,
                        'name'        => __( 'Mahdoton', 'balance-testing' ),
                        'description' => __( 'Et pysty kunnolla tekemään testiä, koska se lähtee kaatamaan jo alussa ja on liian vaikea tehdä. Testin yrittäminen uudelleen tuntuu vaaralliselta.', 'balance-testing' ),
                    ),
                ),
            ),
            'strength' => array(
                'slug'     => 'strength',
                'headers'  => array(
                    __( 'Taso', 'balance-testing' ),
                    __( 'Vaikeustaso', 'balance-testing' ),
                    __( 'Kuvaus ja suoriutuminen', 'balance-testing' ),
                ),
                'rows'     => array(
                    array(
                        'level'       => 1,
                        'name'        => __( 'Erittäin helppo', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään liikettä yli 30 toistoa hallitusti ilman merkittävää väsymistä.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 2,
                        'name'        => __( 'Helppo', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään liikettä 23-29 toistoa hallitusti tuntien jonkun verran väsymistä loppua kohden.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 3,
                        'name'        => __( 'Kohtuullinen', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään liikettä 15-22 kertaa hallitusti tuntien kohtuullista väsymistä loppua kohden.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 4,
                        'name'        => __( 'Haastava', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään liikettä 5-14 kertaa hallitusti tuntien selvää väsymistä loppua kohden.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 5,
                        'name'        => __( 'Erittäin raskas', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään liikettä 1-4 kertaa. Tunnet väsyneisyyttä jo ensimmäisissä toistoissa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 6,
                        'name'        => __( 'Mahdoton', 'balance-testing' ),
                        'description' => __( 'Liike on liian haastava tehdä.', 'balance-testing' ),
                    ),
                ),
            ),
            'habituation' => array(
                'slug'     => 'habituation',
                'headers'  => array(
                    __( 'Taso', 'balance-testing' ),
                    __( 'Vaikeustaso', 'balance-testing' ),
                    __( 'Kuvaus ja suoriutuminen', 'balance-testing' ),
                ),
                'rows'     => array(
                    array(
                        'level'       => 1,
                        'name'        => __( 'Erittäin helppo', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään testin ilman oireiden provosoitumista ja pahenemista.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 2,
                        'name'        => __( 'Helppo', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään testin ilman oireiden provosoitumista ja pahenemista.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 3,
                        'name'        => __( 'Kohtuullinen', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään testin oireiden provosoituessa ja pahentuessa hieman tai jonkun verran. Tilanne tasaantuu alle minuutissa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 4,
                        'name'        => __( 'Haastava', 'balance-testing' ),
                        'description' => __( 'Pystyt tekemään testin oireiden provosoituessa ja pahentuessa selvästi. Tilanne tasaantuu alle 5 minuutissa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 5,
                        'name'        => __( 'Erittäin raskas', 'balance-testing' ),
                        'description' => __( 'Pystyt aloittamaan testin, muttet pysty tekemään pyydettyjä toistomääriä. Oirekuva ei tasaannu viidessä minuutissa.', 'balance-testing' ),
                    ),
                    array(
                        'level'       => 6,
                        'name'        => __( 'Mahdoton', 'balance-testing' ),
                        'description' => __( 'Testi on liian haastava tehdä, koska oireet provosoituvat heti. Testin tekeminen ei tunnu turvalliselta.', 'balance-testing' ),
                    ),
                ),
            ),
        );
    }

    /**
     * @param string $scale_key ACF `test_scale` value.
     * @return array{slug: string, headers: array<int, string>, rows: array<int, array{level: int, name: string, description: string}>}
     */
    public function get_scale( $scale_key ) {
        $scales = $this->get_scales();
        $scale_key = sanitize_key( (string) $scale_key );

        if ( isset( $scales[ $scale_key ] ) ) {
            return $scales[ $scale_key ];
        }

        return $scales['balance'];
    }

    /**
     * Resolve scale key from a test post.
     *
     * @param int $test_id Test post ID.
     * @return string
     */
    public function get_scale_key_for_test( $test_id ) {
        $test_id = absint( $test_id );
        $scale_key = '';

        if ( function_exists( 'get_field' ) ) {
            $scale_key = get_field( 'test_scale', $test_id );
        }

        if ( empty( $scale_key ) ) {
            $scale_key = get_post_meta( $test_id, 'test_scale', true );
        }

        return sanitize_key( (string) $scale_key );
    }
}
