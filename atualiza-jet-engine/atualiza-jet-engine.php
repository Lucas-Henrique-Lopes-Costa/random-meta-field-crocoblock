<?php
/*
Plugin Name: Atualiza Variáveis do Jet Engine
Description: Atualiza os meta fields (distribuicao, minima, padrao, maxima) para o post type "jogos" a cada 10 minutos e permite execução manual via tela. Também possibilita atualizar o campo "link-google-maps" com um valor informado.
Version: 1.2
Author: Seu Nome
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto ao arquivo
}

/**
 * Função auxiliar para gerar números aleatórios com peso:
 *
 * - 50% de chance de um número entre 7 e 30;
 * - 30% de chance de um número entre 30 e 70;
 * - 20% de chance de um número entre 70 e 98.
 *
 * @return int
 */
function aje_get_weighted_random_value() {
    $random = mt_rand(0, 1000) / 1000;
    if ( $random < 0.5 ) {
        return mt_rand(7, 30);
    } elseif ( $random < 0.8 ) {
        return mt_rand(30, 70);
    } else {
        return mt_rand(70, 98);
    }
}

/**
 * Adiciona um novo intervalo de 10 minutos ao WP-Cron.
 */
function aje_cron_schedule( $schedules ) {
    if ( ! isset( $schedules['a_cada_10_minutos'] ) ) {
        $schedules['a_cada_10_minutos'] = array(
            'interval' => 600, // 600 segundos = 10 minutos
            'display'  => __( 'A cada 10 minutos' ),
        );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'aje_cron_schedule' );

/**
 * Agenda o evento na ativação do plugin.
 */
function aje_ativar_plugin() {
    if ( ! wp_next_scheduled( 'aje_atualizar_variaveis_evento' ) ) {
        wp_schedule_event( time(), 'a_cada_10_minutos', 'aje_atualizar_variaveis_evento' );
    }
}
register_activation_hook( __FILE__, 'aje_ativar_plugin' );

/**
 * Remove o evento agendado na desativação do plugin.
 */
function aje_desativar_plugin() {
    wp_clear_scheduled_hook( 'aje_atualizar_variaveis_evento' );
}
register_deactivation_hook( __FILE__, 'aje_desativar_plugin' );

/**
 * Função que atualiza os meta fields dos posts do tipo "jogos" utilizando valores aleatórios com peso.
 */
function aje_atualizar_variaveis() {
    // Query para obter todos os posts do tipo "jogos"
    $args  = array(
        'post_type'      => 'jogos',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );
    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            // Gera novos valores com a função de peso
            $novo_valor_distribuicao = aje_get_weighted_random_value();
            $novo_valor_minima       = aje_get_weighted_random_value();
            $novo_valor_padrao       = aje_get_weighted_random_value();
            $novo_valor_maxima       = aje_get_weighted_random_value();

            // Atualiza os meta fields
            update_post_meta( $post_id, 'distribuicao', $novo_valor_distribuicao );
            update_post_meta( $post_id, 'minima', $novo_valor_minima );
            update_post_meta( $post_id, 'padrao', $novo_valor_padrao );
            update_post_meta( $post_id, 'maxima', $novo_valor_maxima );
        }
        wp_reset_postdata();
    }
}
add_action( 'aje_atualizar_variaveis_evento', 'aje_atualizar_variaveis' );

/**
 * Função para atualizar o campo 'link-google-maps' com um valor específico.
 */
function aje_atualizar_link_google_maps( $novo_link ) {
    // Query para obter todos os posts do tipo "jogos"
    $args  = array(
        'post_type'      => 'jogos',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );
    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            update_post_meta( $post_id, 'link-google-maps', $novo_link );
        }
        wp_reset_postdata();
    }
}

/**
 * Adiciona um item de menu para forçar a execução manual do script.
 */
function aje_adicionar_menu_manual() {
    add_submenu_page(
        'tools.php',                     // Página pai: Ferramentas
        'Forçar Execução do Script',     // Título da página
        'Forçar Execução',               // Título do menu
        'manage_options',                // Capacidade necessária
        'aje-forcar-execucao',           // Slug da página
        'aje_executar_manual_callback'   // Função que renderiza a página
    );
}
add_action( 'admin_menu', 'aje_adicionar_menu_manual' );

/**
 * Renderiza a página que permite forçar a execução.
 */
function aje_executar_manual_callback() {
    // Verifica se o formulário foi submetido
    if ( isset( $_POST['aje_executar'] ) ) {
        // Atualiza os meta fields com os valores aleatórios com peso
        aje_atualizar_variaveis();

        // Se um link para Google Maps foi informado, atualiza também esse campo
        if ( ! empty( $_POST['link_google_maps'] ) ) {
            $novo_link = esc_url_raw( trim( $_POST['link_google_maps'] ) );
            aje_atualizar_link_google_maps( $novo_link );
        }

        echo '<div class="notice notice-success is-dismissible"><p>Script executado manualmente!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Forçar Execução do Script</h1>
        <p>Clique no botão abaixo para executar o script e atualizar os meta fields dos posts do tipo <strong>jogos</strong>.</p>
        <p>Se desejar atualizar também o campo <strong>link-google-maps</strong>, insira o novo link abaixo:</p>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="link_google_maps">Link Google Maps</label></th>
                    <td>
                        <input name="link_google_maps" type="text" id="link_google_maps" value="" class="regular-text" placeholder="Digite o novo link do Google Maps">
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Executar Script', 'primary', 'aje_executar' ); ?>
        </form>
    </div>
    <?php
}
