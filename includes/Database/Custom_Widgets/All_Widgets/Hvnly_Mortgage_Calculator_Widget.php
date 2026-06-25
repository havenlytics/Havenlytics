<?php
/**
 * Mortgage Calculator Widget for Havenlytics
 *
 * @package HvnlyNab\Database\Custom_Widgets\All_Widgets
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Widgets\All_Widgets;

use HvnlyNab\Database\Custom_Widgets\WidgetInstanceHelpers;
use HvnlyNab\Services\Hvnly_Price_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hvnly_Mortgage_Calculator_Widget
 */
class Hvnly_Mortgage_Calculator_Widget extends \WP_Widget {

	/**
	 * Whether frontend assets were enqueued this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'hvnly-property-single__widget hvnly-property-single__widget--mortgage',
			'description'                 => __( 'Display an advanced mortgage calculator for properties.', 'havenlytics' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct(
			'hvnly_mortgage_calculator',
			__( 'Mortgage Calculator', 'havenlytics' ),
			$widget_ops
		);
	}

	/**
	 * Widget frontend display
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! is_singular( 'hvnly_property' ) ) {
			return;
		}

		$instance  = $this->sanitize_instance( $instance );
		$title     = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Mortgage Calculator', 'havenlytics' );
		$property_id = get_the_ID();
		$resolved  = Hvnly_Price_Resolver::resolve( $property_id );

		echo wp_kses_post( $args['before_widget'] );
		echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );

		$this->enqueue_styles();

		if ( empty( $resolved['is_calculable'] ) || empty( $resolved['numeric_price'] ) || $resolved['numeric_price'] <= 0 ) {
			$this->render_unavailable_state( $resolved );
			echo wp_kses_post( $args['after_widget'] );
			return;
		}

		$this->enqueue_scripts();
		$this->render_calculator( $instance, $property_id, $resolved );

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Render placeholder / non-numeric price notice.
	 *
	 * @param array $resolved Price resolver result.
	 */
	private function render_unavailable_state( array $resolved ) {
		$display = ! empty( $resolved['placeholder_label'] )
			? $resolved['placeholder_label']
			: $resolved['display_price'];

		$message = apply_filters(
			'hvnly_mortgage_unavailable_message',
			__( 'Mortgage calculations are unavailable because this property does not have a numeric asking price.', 'havenlytics' )
		);
		?>
<div class="hvnly-mortgage-unavailable" role="status" aria-live="polite">
    <?php if ( ! empty( $display ) ) : ?>
    <span class="hvnly-mortgage-unavailable__price"><?php echo esc_html( $display ); ?></span>
    <?php endif; ?>
    <p class="hvnly-mortgage-unavailable__message"><?php echo esc_html( $message ); ?></p>
</div>
<?php
	}

	/**
	 * Render interactive calculator.
	 *
	 * @param array $instance    Widget instance.
	 * @param int   $property_id Property ID.
	 * @param array $resolved    Price resolver result.
	 */
	private function render_calculator( array $instance, $property_id, array $resolved ) {
		$default_interest_rate          = isset( $instance['default_interest_rate'] ) ? floatval( $instance['default_interest_rate'] ) : 4.5;
		$show_advanced                  = ! empty( $instance['show_advanced'] );
		$default_term                   = ! empty( $instance['default_term'] ) ? absint( $instance['default_term'] ) : 30;
		$default_down_payment_percent   = ! empty( $instance['default_down_payment_percent'] ) ? absint( $instance['default_down_payment_percent'] ) : 10;
		$show_property_tax              = ! empty( $instance['show_property_tax'] );
		$show_home_insurance            = ! empty( $instance['show_home_insurance'] );
		$show_hoa_fees                  = ! empty( $instance['show_hoa_fees'] );
		$show_pmi                       = ! empty( $instance['show_pmi'] );
		$show_closing_costs             = ! empty( $instance['show_closing_costs'] );
		$show_amortization              = ! empty( $instance['show_amortization'] );
		$show_currency_selector         = ! empty( $instance['show_currency_selector'] );

		$numeric_price = (float) $resolved['numeric_price'];
		$currency      = $this->get_currency_context();
		$currency_symbol   = $currency['currency_symbol'];
		$currency_position = strtolower( $currency['currency_position'] );

		$default_down_payment = $numeric_price * ( $default_down_payment_percent / 100 );

		$stored_tax = get_post_meta( $property_id, '_hvnly_property_annual_tax_amount', true );
		$stored_hoa = get_post_meta( $property_id, '_hvnly_property_hoa_fee', true );

		$default_tax = $show_property_tax
			? ( is_numeric( $stored_tax ) && (float) $stored_tax > 0 ? (float) $stored_tax : $numeric_price * 0.01 )
			: 0;
		$default_insurance = $show_home_insurance ? $numeric_price * 0.0035 : 0;
		$default_hoa       = $show_hoa_fees && is_numeric( $stored_hoa ) ? (float) $stored_hoa : 0;
		$default_pmi_rate  = 0.5;
		$default_closing   = $show_closing_costs ? $numeric_price * 0.03 : 0;

		$js_config = array(
			'propertyPrice'      => $numeric_price,
			'defaultDownPercent' => $default_down_payment_percent,
			'defaultInterest'    => $default_interest_rate,
			'defaultTerm'        => $default_term,
			'currencySymbol'     => $currency_symbol,
			'currencyPosition'   => $currency_position,
			'mortgageMode'       => Hvnly_Price_Resolver::get_mortgage_mode(),
			'showPropertyTax'    => $show_property_tax,
			'showHomeInsurance'  => $show_home_insurance,
			'showHoaFees'        => $show_hoa_fees,
			'showPmi'            => $show_pmi,
			'showClosingCosts'   => $show_closing_costs,
			'showAmortization'   => $show_amortization,
			'defaultTax'         => round( $default_tax ),
			'defaultInsurance'   => round( $default_insurance ),
			'defaultHoa'         => $default_hoa,
			'defaultPmiRate'     => $default_pmi_rate,
			'defaultClosingCosts'=> round( $default_closing ),
		);

		$widget_id = $this->id;
		?>
<div class="hvnly-mortgage-calculator-root" data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
    data-hvnly-mortgage-config="<?php echo esc_attr( wp_json_encode( $js_config ) ); ?>">

    <?php if ( $show_currency_selector ) : ?>
    <div class="hvnly-mortgage-currency-selector" style="margin-bottom: var(--hvnly-space-md);">
        <label for="hvnlyCurrency_<?php echo esc_attr( $widget_id ); ?>"
            style="display: block; margin-bottom: var(--hvnly-space-xs); font-size: var(--hvnly-font-size-sm);">
            <?php esc_html_e( 'Currency:', 'havenlytics' ); ?>
        </label>
        <select class="hvnly-property-single__form-input hvnly-mortgage-currency-select"
            id="hvnlyCurrency_<?php echo esc_attr( $widget_id ); ?>"
            data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
            aria-label="<?php esc_attr_e( 'Currency', 'havenlytics' ); ?>">
            <option value="<?php echo esc_attr( $currency_symbol ); ?>" selected>
                <?php echo esc_html( $currency['currency_code'] . ' (' . $currency_symbol . ')' ); ?>
            </option>
        </select>
        <small><?php esc_html_e( 'Currency is synchronized with global settings', 'havenlytics' ); ?></small>
    </div>
    <?php endif; ?>

    <div class="hvnly-mortgage-price-display">
        <span class="hvnly-mortgage-price-label"><?php esc_html_e( 'Property Price', 'havenlytics' ); ?></span>
        <span class="hvnly-mortgage-price-value" id="hvnlyPropertyPrice_<?php echo esc_attr( $widget_id ); ?>"
            data-currency="<?php echo esc_attr( $currency_symbol ); ?>"
            data-position="<?php echo esc_attr( $currency_position ); ?>">
            <?php echo wp_kses_post( $resolved['display_price'] ); ?>
        </span>
    </div>

    <div class="hvnly-mortgage-downpayment">
        <div class="hvnly-mortgage-downpayment-header">
            <span><?php esc_html_e( 'Down Payment', 'havenlytics' ); ?></span>
            <span class="hvnly-mortgage-downpayment-percent"
                id="hvnlyDownPaymentPercent_<?php echo esc_attr( $widget_id ); ?>">
                <?php echo absint( $default_down_payment_percent ); ?>%
            </span>
        </div>
        <div class="hvnly-mortgage-downpayment-inputs">
            <div class="hvnly-mortgage-amount-input">
                <span class="currency-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
                <input type="number" class="hvnly-property-single__form-input"
                    id="hvnlyDownPaymentAmount_<?php echo esc_attr( $widget_id ); ?>"
                    value="<?php echo esc_attr( round( $default_down_payment ) ); ?>" step="1000" min="0"
                    max="<?php echo esc_attr( round( $numeric_price ) ); ?>"
                    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
                    aria-label="<?php esc_attr_e( 'Down payment amount', 'havenlytics' ); ?>">
            </div>
            <div class="hvnly-mortgage-slider-input">
                <input type="range" id="hvnlyDownPaymentSlider_<?php echo esc_attr( $widget_id ); ?>" min="0" max="50"
                    value="<?php echo esc_attr( $default_down_payment_percent ); ?>" step="1"
                    data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
                    aria-label="<?php esc_attr_e( 'Down payment percentage', 'havenlytics' ); ?>">
            </div>
        </div>
    </div>

    <div class="hvnly-mortgage-term-section">
        <span class="hvnly-mortgage-term-label"><?php esc_html_e( 'Loan Term', 'havenlytics' ); ?></span>
        <div class="hvnly-mortgage-term-buttons" role="group"
            aria-label="<?php esc_attr_e( 'Loan term', 'havenlytics' ); ?>">
            <?php foreach ( array( 30, 20, 15, 10 ) as $term_years ) : ?>
            <button type="button"
                class="hvnly-mortgage-term-btn <?php echo (int) $default_term === (int) $term_years ? 'active' : ''; ?>"
                data-term="<?php echo esc_attr( $term_years ); ?>"
                data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
                <?php echo esc_html(
                    /* translators: %d: number of years for loan term */
                     sprintf( __( '%d yrs', 'havenlytics' ), $term_years ) ); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="hvnly-mortgage-interest">
        <div class="hvnly-mortgage-interest-header">
            <span><?php esc_html_e( 'Interest Rate', 'havenlytics' ); ?></span>
            <span class="hvnly-mortgage-market-rate"><?php esc_html_e( 'Market Rate', 'havenlytics' ); ?></span>
        </div>
        <div class="hvnly-mortgage-interest-input">
            <input type="number" class="hvnly-property-single__form-input"
                id="hvnlyInterestRate_<?php echo esc_attr( $widget_id ); ?>"
                value="<?php echo esc_attr( $default_interest_rate ); ?>" step="0.125" min="0.1" max="15"
                data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
                aria-label="<?php esc_attr_e( 'Interest rate percentage', 'havenlytics' ); ?>">
            <span class="percent-symbol">%</span>
        </div>
    </div>

    <?php if ( $show_advanced && ( $show_property_tax || $show_home_insurance || $show_hoa_fees || $show_pmi || $show_closing_costs ) ) : ?>
    <button type="button" class="hvnly-mortgage-advanced-toggle"
        id="hvnlyToggleAdvanced_<?php echo esc_attr( $widget_id ); ?>"
        data-widget-id="<?php echo esc_attr( $widget_id ); ?>" aria-expanded="false"
        aria-controls="hvnlyAdvancedPanel_<?php echo esc_attr( $widget_id ); ?>">
        <svg class="hvnly-icon hvnly-icon-sm" aria-hidden="true">
            <use xlink:href="#hvnly-chevron-right"></use>
        </svg>
        <?php esc_html_e( 'Advanced Options', 'havenlytics' ); ?>
    </button>

    <div class="hvnly-mortgage-advanced-panel" id="hvnlyAdvancedPanel_<?php echo esc_attr( $widget_id ); ?>">
        <?php if ( $show_property_tax ) : ?>
        <div class="hvnly-property-single__form-group" style="margin-bottom: var(--hvnly-space-md);">
            <label class="hvnly-mortgage-field-label" for="hvnlyPropertyTax_<?php echo esc_attr( $widget_id ); ?>">
                <?php esc_html_e( 'Annual Property Tax', 'havenlytics' ); ?>
            </label>
            <div class="hvnly-mortgage-input-with-symbol">
                <span class="currency-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
                <input type="number" class="hvnly-property-single__form-input hvnly-mortgage-input-with-padding"
                    id="hvnlyPropertyTax_<?php echo esc_attr( $widget_id ); ?>"
                    value="<?php echo esc_attr( round( $default_tax ) ); ?>" step="500" min="0"
                    data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_home_insurance ) : ?>
        <div class="hvnly-property-single__form-group" style="margin-bottom: var(--hvnly-space-md);">
            <label class="hvnly-mortgage-field-label" for="hvnlyHomeInsurance_<?php echo esc_attr( $widget_id ); ?>">
                <?php esc_html_e( 'Annual Home Insurance', 'havenlytics' ); ?>
            </label>
            <div class="hvnly-mortgage-input-with-symbol">
                <span class="currency-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
                <input type="number" class="hvnly-property-single__form-input hvnly-mortgage-input-with-padding"
                    id="hvnlyHomeInsurance_<?php echo esc_attr( $widget_id ); ?>"
                    value="<?php echo esc_attr( round( $default_insurance ) ); ?>" step="100" min="0"
                    data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_hoa_fees ) : ?>
        <div class="hvnly-property-single__form-group" style="margin-bottom: var(--hvnly-space-md);">
            <label class="hvnly-mortgage-field-label" for="hvnlyHOA_<?php echo esc_attr( $widget_id ); ?>">
                <?php esc_html_e( 'Monthly HOA Fees', 'havenlytics' ); ?>
            </label>
            <div class="hvnly-mortgage-input-with-symbol">
                <span class="currency-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
                <input type="number" class="hvnly-property-single__form-input hvnly-mortgage-input-with-padding"
                    id="hvnlyHOA_<?php echo esc_attr( $widget_id ); ?>" value="<?php echo esc_attr( $default_hoa ); ?>"
                    step="50" min="0" data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_pmi ) : ?>
        <div class="hvnly-property-single__form-group" style="margin-bottom: var(--hvnly-space-md);">
            <label class="hvnly-mortgage-field-label" for="hvnlyPMIRate_<?php echo esc_attr( $widget_id ); ?>">
                <?php esc_html_e( 'PMI Rate (%)', 'havenlytics' ); ?>
                <span
                    class="hvnly-mortgage-field-note"><?php esc_html_e( '(if down payment < 20%)', 'havenlytics' ); ?></span>
            </label>
            <input type="number" class="hvnly-property-single__form-input"
                id="hvnlyPMIRate_<?php echo esc_attr( $widget_id ); ?>"
                value="<?php echo esc_attr( $default_pmi_rate ); ?>" step="0.1" min="0" max="5"
                data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
            <span class="hvnly-input-suffix">%</span>
        </div>
        <?php endif; ?>

        <?php if ( $show_closing_costs ) : ?>
        <div class="hvnly-property-single__form-group" style="margin-bottom: var(--hvnly-space-md);">
            <label class="hvnly-mortgage-field-label" for="hvnlyClosingCosts_<?php echo esc_attr( $widget_id ); ?>">
                <?php esc_html_e( 'Estimated Closing Costs', 'havenlytics' ); ?>
            </label>
            <div class="hvnly-mortgage-input-with-symbol">
                <span class="currency-symbol"><?php echo esc_html( $currency_symbol ); ?></span>
                <input type="number" class="hvnly-property-single__form-input hvnly-mortgage-input-with-padding"
                    id="hvnlyClosingCosts_<?php echo esc_attr( $widget_id ); ?>"
                    value="<?php echo esc_attr( round( $default_closing ) ); ?>" step="1000" min="0"
                    data-widget-id="<?php echo esc_attr( $widget_id ); ?>">
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="hvnly-property-single__mortgage-result">
        <div class="hvnly-mortgage-result-header">
            <span class="hvnly-mortgage-result-label"><?php esc_html_e( 'Monthly Payment', 'havenlytics' ); ?></span>
            <span class="hvnly-property-single__mortgage-amount"
                id="hvnlyMonthlyPayment_<?php echo esc_attr( $widget_id ); ?>" aria-live="polite" aria-atomic="true">
                <?php echo esc_html( $currency_symbol ); ?>0
            </span>
        </div>

        <div class="hvnly-mortgage-breakdown">
            <div class="hvnly-mortgage-breakdown-item">
                <span><?php esc_html_e( 'Principal & Interest', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyPrincipalInterest_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <?php if ( $show_property_tax ) : ?>
            <div class="hvnly-mortgage-breakdown-item">
                <span><?php esc_html_e( 'Property Tax', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyMonthlyTax_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <?php endif; ?>
            <?php if ( $show_home_insurance ) : ?>
            <div class="hvnly-mortgage-breakdown-item">
                <span><?php esc_html_e( 'Home Insurance', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyMonthlyInsurance_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <?php endif; ?>
            <?php if ( $show_hoa_fees ) : ?>
            <div class="hvnly-mortgage-breakdown-item">
                <span><?php esc_html_e( 'HOA Fees', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyMonthlyHOA_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <?php endif; ?>
            <?php if ( $show_pmi ) : ?>
            <div class="hvnly-mortgage-breakdown-item">
                <span><?php esc_html_e( 'PMI', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyMonthlyPMI_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="hvnly-mortgage-summary">
            <div class="hvnly-mortgage-summary-item">
                <span><?php esc_html_e( 'Loan Amount:', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyLoanAmount_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <div class="hvnly-mortgage-summary-item">
                <span><?php esc_html_e( 'Down Payment:', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyDisplayDownPayment_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0
                    (0%)</span>
            </div>
            <div class="hvnly-mortgage-summary-item">
                <span><?php esc_html_e( 'Total Interest:', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyTotalInterest_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
            <div class="hvnly-mortgage-summary-item">
                <span><?php esc_html_e( 'Total Payments:', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyTotalPayments_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
        </div>

        <?php if ( $show_closing_costs ) : ?>
        <div class="hvnly-mortgage-closing-costs">
            <div class="hvnly-mortgage-summary-item">
                <span><?php esc_html_e( 'Est. Closing Costs:', 'havenlytics' ); ?></span>
                <span
                    id="hvnlyClosingCostsDisplay_<?php echo esc_attr( $widget_id ); ?>"><?php echo esc_html( $currency_symbol ); ?>0</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( $show_amortization ) : ?>
    <div class="hvnly-mortgage-amortization" style="margin-top: var(--hvnly-space-lg);">
        <button type="button" class="hvnly-mortgage-amortization-toggle"
            id="hvnlyAmortizationToggle_<?php echo esc_attr( $widget_id ); ?>"
            data-widget-id="<?php echo esc_attr( $widget_id ); ?>" aria-expanded="false"
            aria-controls="hvnlyAmortizationPanel_<?php echo esc_attr( $widget_id ); ?>">
            <svg class="hvnly-icon hvnly-icon-sm" aria-hidden="true">
                <use xlink:href="#hvnly-chevron-right"></use>
            </svg>
            <?php esc_html_e( 'View Amortization Schedule', 'havenlytics' ); ?>
        </button>
        <div class="hvnly-mortgage-amortization-panel" id="hvnlyAmortizationPanel_<?php echo esc_attr( $widget_id ); ?>"
            style="display: none;">
            <div class="hvnly-mortgage-amortization-table-container">
                <table class="hvnly-mortgage-amortization-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Year', 'havenlytics' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Principal Paid', 'havenlytics' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Interest Paid', 'havenlytics' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Balance', 'havenlytics' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="hvnlyAmortizationTable_<?php echo esc_attr( $widget_id ); ?>"></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <p class="hvnly-mortgage-note">
        *<?php esc_html_e( 'Estimated payment for informational purposes only. Actual rates may vary.', 'havenlytics' ); ?>
    </p>
</div>
<?php
	}

	/**
	 * Enqueue calculator styles once per request.
	 */
	private function enqueue_styles() {
		static $styles_enqueued = false;

		if ( $styles_enqueued ) {
			return;
		}

		$version    = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '2.2.1';
		$style_deps = wp_style_is( 'hvnly-frontend-widgets', 'registered' ) ? array( 'hvnly-frontend-widgets' ) : array();

		if ( wp_style_is( 'hvnly-frontend-widgets', 'registered' ) ) {
			wp_enqueue_style( 'hvnly-frontend-widgets' );
		}

		wp_enqueue_style(
			'hvnly-mortgage-calculator',
			HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-mortgage-calculator.css',
			$style_deps,
			$version
		);

		$styles_enqueued = true;
	}

	/**
	 * Enqueue calculator script once per request.
	 */
	private function enqueue_scripts() {
		if ( self::$assets_enqueued ) {
			return;
		}

		$version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '2.2.1';

		wp_enqueue_script(
			'hvnly-mortgage-calculator',
			HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-mortgage-calculator.js',
			array(),
			$version,
			true
		);

		self::$assets_enqueued = true;
	}

	/**
	 * Currency context from global settings.
	 *
	 * @return array
	 */
	private function get_currency_context() {
		$settings = HVNLY_NAB()->Helper->get_currency_settings();

		return array(
			'currency_code'     => $settings['hvnly_currencyType'] ?? 'USD',
			'currency_symbol'   => HVNLY_NAB()->Helper->get_current_currency_symbol(),
			'currency_position' => $settings['hvnly_currencyPositionType'] ?? 'LEFT',
		);
	}

	/**
	 * Widget form (admin)
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$instance = WidgetInstanceHelpers::normalize_instance( $this->sanitize_instance( $instance ) );

		$title                        = isset( $instance['title'] ) ? $instance['title'] : __( 'Mortgage Calculator', 'havenlytics' );
		$default_interest_rate        = isset( $instance['default_interest_rate'] ) ? $instance['default_interest_rate'] : 4.5;
		$default_term                 = isset( $instance['default_term'] ) ? $instance['default_term'] : 30;
		$default_down_payment_percent = isset( $instance['default_down_payment_percent'] ) ? $instance['default_down_payment_percent'] : 10;

		$show_advanced            = ! empty( $instance['show_advanced'] );
		$show_property_tax        = ! empty( $instance['show_property_tax'] );
		$show_home_insurance      = ! empty( $instance['show_home_insurance'] );
		$show_hoa_fees            = ! empty( $instance['show_hoa_fees'] );
		$show_pmi                 = ! empty( $instance['show_pmi'] );
		$show_closing_costs       = ! empty( $instance['show_closing_costs'] );
		$show_amortization        = ! empty( $instance['show_amortization'] );
		$show_currency_selector   = ! empty( $instance['show_currency_selector'] );

		$currency = $this->get_currency_context();
		$current_currency = $currency['currency_code'] . ' (' . $currency['currency_symbol'] . ')';
		?>

<div class="hvnly-widget-fields">
    <p>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'havenlytics' ); ?></label>
        <input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
            value="<?php echo esc_attr( $title ); ?>">
    </p>

    <p>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'default_interest_rate' ) ); ?>"><?php esc_html_e( 'Default Interest Rate (%):', 'havenlytics' ); ?></label>
        <input type="number" class="widefat"
            id="<?php echo esc_attr( $this->get_field_id( 'default_interest_rate' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'default_interest_rate' ) ); ?>"
            value="<?php echo esc_attr( $default_interest_rate ); ?>" step="0.125" min="0.1" max="15">
    </p>

    <p>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'default_term' ) ); ?>"><?php esc_html_e( 'Default Loan Term (years):', 'havenlytics' ); ?></label>
        <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'default_term' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'default_term' ) ); ?>">
            <?php foreach ( array( 30, 20, 15, 10 ) as $term_option ) : ?>
            <option value="<?php echo esc_attr( $term_option ); ?>" <?php selected( $default_term, $term_option ); ?>>
                <?php echo esc_html(
                    /* translators: %d: number of years for loan term */
                     sprintf( __( '%d years', 'havenlytics' ), $term_option ) ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'default_down_payment_percent' ) ); ?>"><?php esc_html_e( 'Default Down Payment (%):', 'havenlytics' ); ?></label>
        <input type="number" class="widefat"
            id="<?php echo esc_attr( $this->get_field_id( 'default_down_payment_percent' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'default_down_payment_percent' ) ); ?>"
            value="<?php echo esc_attr( $default_down_payment_percent ); ?>" min="0" max="50" step="1">
    </p>

    <p class="hvnly-widget-section-title"
        style="font-weight: 600; margin: 15px 0 5px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
        <?php esc_html_e( 'Currency Settings', 'havenlytics' ); ?>
    </p>

    <p>
        <strong><?php esc_html_e( 'Current Currency:', 'havenlytics' ); ?></strong>
        <?php echo esc_html( $current_currency ); ?>
        <br>
        <small><?php esc_html_e( 'Currency settings are managed globally in Havenlytics Settings.', 'havenlytics' ); ?></small>
    </p>

    <p>
        <input type="checkbox" class="checkbox"
            id="<?php echo esc_attr( $this->get_field_id( 'show_currency_selector' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'show_currency_selector' ) ); ?>" value="1"
            <?php checked( $show_currency_selector ); ?>>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'show_currency_selector' ) ); ?>"><?php esc_html_e( 'Show currency info', 'havenlytics' ); ?></label>
    </p>

    <p class="hvnly-widget-section-title"
        style="font-weight: 600; margin: 15px 0 5px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
        <?php esc_html_e( 'Advanced Options', 'havenlytics' ); ?>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_advanced' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'show_advanced' ) ); ?>" value="1"
            <?php checked( $show_advanced ); ?>>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'show_advanced' ) ); ?>"><?php esc_html_e( 'Enable advanced options panel', 'havenlytics' ); ?></label>
    </p>

    <div class="hvnly-widget-subsection" style="margin-left: 20px;">
        <?php
		$advanced_fields = array(
			'show_property_tax'   => __( 'Show property tax field', 'havenlytics' ),
			'show_home_insurance' => __( 'Show home insurance field', 'havenlytics' ),
			'show_hoa_fees'       => __( 'Show HOA fees field', 'havenlytics' ),
			'show_pmi'            => __( 'Show PMI (Private Mortgage Insurance)', 'havenlytics' ),
			'show_closing_costs'  => __( 'Show closing costs field', 'havenlytics' ),
		);
		foreach ( $advanced_fields as $field_key => $field_label ) :
			?>
        <p>
            <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( $field_key ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( $field_key ) ); ?>" value="1"
                <?php checked( ! empty( $instance[ $field_key ] ) ); ?>>
            <label
                for="<?php echo esc_attr( $this->get_field_id( $field_key ) ); ?>"><?php echo esc_html( $field_label ); ?></label>
        </p>
        <?php endforeach; ?>
    </div>

    <p class="hvnly-widget-section-title"
        style="font-weight: 600; margin: 15px 0 5px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
        <?php esc_html_e( 'Additional Features', 'havenlytics' ); ?>
    </p>

    <p>
        <input type="checkbox" class="checkbox"
            id="<?php echo esc_attr( $this->get_field_id( 'show_amortization' ) ); ?>"
            name="<?php echo esc_attr( $this->get_field_name( 'show_amortization' ) ); ?>" value="1"
            <?php checked( $show_amortization ); ?>>
        <label
            for="<?php echo esc_attr( $this->get_field_id( 'show_amortization' ) ); ?>"><?php esc_html_e( 'Show amortization schedule', 'havenlytics' ); ?></label>
    </p>
</div>

<style>
.hvnly-widget-fields p {
    margin: 1em 0;
}

.hvnly-widget-subsection {
    border-left: 2px solid #6c60fe;
    padding-left: 15px;
    margin-left: 10px;
    margin-bottom: 15px;
}
</style>
<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$old_instance = WidgetInstanceHelpers::normalize_instance( $this->sanitize_instance( $old_instance ) );
		$new_instance = is_array( $new_instance ) ? $new_instance : array();

		$sanitized = array(
			'title'                        => sanitize_text_field( (string) ( $new_instance['title'] ?? $old_instance['title'] ?? __( 'Mortgage Calculator', 'havenlytics' ) ) ),
			'default_interest_rate'        => floatval( $new_instance['default_interest_rate'] ?? $old_instance['default_interest_rate'] ?? 4.5 ),
			'default_term'                 => absint( $new_instance['default_term'] ?? $old_instance['default_term'] ?? 30 ),
			'default_down_payment_percent' => absint( $new_instance['default_down_payment_percent'] ?? $old_instance['default_down_payment_percent'] ?? 10 ),
		);

		$checkboxes = array(
			'show_advanced',
			'show_property_tax',
			'show_home_insurance',
			'show_hoa_fees',
			'show_pmi',
			'show_closing_costs',
			'show_amortization',
			'show_currency_selector',
		);

		foreach ( $checkboxes as $key ) {
			if ( array_key_exists( $key, $new_instance ) ) {
				$sanitized[ $key ] = ! empty( $new_instance[ $key ] );
			}
		}

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}

	/**
	 * Sanitize instance values
	 *
	 * @param array $instance Instance.
	 * @return array
	 */
	private function sanitize_instance( $instance ) {
		$instance = WidgetInstanceHelpers::normalize_instance( $instance );

		$defaults = array(
			'title'                        => __( 'Mortgage Calculator', 'havenlytics' ),
			'default_interest_rate'        => 4.5,
			'default_term'                 => 30,
			'default_down_payment_percent' => 10,
			'show_advanced'                => false,
			'show_property_tax'            => false,
			'show_home_insurance'          => false,
			'show_hoa_fees'                => false,
			'show_pmi'                     => false,
			'show_closing_costs'           => false,
			'show_amortization'            => false,
			'show_currency_selector'       => false,
		);

		$instance = wp_parse_args( $instance, $defaults );

		$instance['title']                        = sanitize_text_field( $instance['title'] );
		$instance['default_interest_rate']        = floatval( $instance['default_interest_rate'] );
		$instance['default_term']               = absint( $instance['default_term'] );
		$instance['default_down_payment_percent'] = absint( $instance['default_down_payment_percent'] );

		foreach ( array_keys( $defaults ) as $key ) {
			if ( strpos( $key, 'show_' ) === 0 ) {
				$instance[ $key ] = (bool) $instance[ $key ];
			}
		}

		return $instance;
	}
}