/**
 * Havenlytics Mortgage Calculator (sidebar widget).
 *
 * @package Havenlytics
 */
(function () {
	'use strict';

	/**
	 * @param {string} widgetId
	 * @param {object} config
	 */
	function HvnlyMortgageCalculatorInstance(widgetId, config) {
		this.widgetId = widgetId;
		this.propertyPrice = parseFloat(config.propertyPrice) || 0;
		this.currencySymbol = config.currencySymbol || '$';
		this.currencyPosition = config.currencyPosition || 'left';
		this.mortgageMode = config.mortgageMode || 'international';
		this.showPropertyTax = !!config.showPropertyTax;
		this.showHomeInsurance = !!config.showHomeInsurance;
		this.showHoaFees = !!config.showHoaFees;
		this.showPmi = !!config.showPmi;
		this.showClosingCosts = !!config.showClosingCosts;
		this.showAmortization = !!config.showAmortization;

		this.downAmount = document.getElementById('hvnlyDownPaymentAmount_' + widgetId);
		this.downSlider = document.getElementById('hvnlyDownPaymentSlider_' + widgetId);
		this.downPercent = document.getElementById('hvnlyDownPaymentPercent_' + widgetId);
		this.interestRate = document.getElementById('hvnlyInterestRate_' + widgetId);
		this.termBtns = document.querySelectorAll('.hvnly-mortgage-term-btn[data-widget-id="' + widgetId + '"]');

		this.monthlyPayment = document.getElementById('hvnlyMonthlyPayment_' + widgetId);
		this.principalInterest = document.getElementById('hvnlyPrincipalInterest_' + widgetId);
		this.monthlyTax = document.getElementById('hvnlyMonthlyTax_' + widgetId);
		this.monthlyInsurance = document.getElementById('hvnlyMonthlyInsurance_' + widgetId);
		this.monthlyHOA = document.getElementById('hvnlyMonthlyHOA_' + widgetId);
		this.monthlyPMI = document.getElementById('hvnlyMonthlyPMI_' + widgetId);
		this.loanAmount = document.getElementById('hvnlyLoanAmount_' + widgetId);
		this.displayDownPayment = document.getElementById('hvnlyDisplayDownPayment_' + widgetId);
		this.totalInterest = document.getElementById('hvnlyTotalInterest_' + widgetId);
		this.totalPayments = document.getElementById('hvnlyTotalPayments_' + widgetId);
		this.closingCostsDisplay = document.getElementById('hvnlyClosingCostsDisplay_' + widgetId);
		this.amortizationTable = document.getElementById('hvnlyAmortizationTable_' + widgetId);

		this.propertyTax = document.getElementById('hvnlyPropertyTax_' + widgetId);
		this.homeInsurance = document.getElementById('hvnlyHomeInsurance_' + widgetId);
		this.hoaFees = document.getElementById('hvnlyHOA_' + widgetId);
		this.pmiRate = document.getElementById('hvnlyPMIRate_' + widgetId);
		this.closingCosts = document.getElementById('hvnlyClosingCosts_' + widgetId);
		this.toggleBtn = document.getElementById('hvnlyToggleAdvanced_' + widgetId);
		this.advancedPanel = document.getElementById('hvnlyAdvancedPanel_' + widgetId);
		this.amortizationToggle = document.getElementById('hvnlyAmortizationToggle_' + widgetId);
		this.amortizationPanel = document.getElementById('hvnlyAmortizationPanel_' + widgetId);
		this.currencySelect = document.getElementById('hvnlyCurrency_' + widgetId);

		this.currentDownPayment = this.propertyPrice * ((parseFloat(config.defaultDownPercent) || 0) / 100);
		this.currentDownPercent = parseFloat(config.defaultDownPercent) || 0;
		this.currentInterestRate = parseFloat(config.defaultInterest) || 4.5;
		this.currentTerm = parseInt(config.defaultTerm, 10) || 30;

		this.currentPropertyTax = this.showPropertyTax ? (parseFloat(config.defaultTax) || 0) : 0;
		this.currentHomeInsurance = this.showHomeInsurance ? (parseFloat(config.defaultInsurance) || 0) : 0;
		this.currentHoaFees = parseFloat(config.defaultHoa) || 0;
		this.currentPmiRate = parseFloat(config.defaultPmiRate) || 0.5;
		this.currentClosingCosts = this.showClosingCosts ? (parseFloat(config.defaultClosingCosts) || 0) : 0;

		this.bindEvents();
		this.initAdvancedToggle();
		this.initAmortizationToggle();
		this.initCurrencySelector();
		this.calculate();
	}

	HvnlyMortgageCalculatorInstance.prototype.bindEvents = function () {
		var self = this;

		if (this.downAmount) {
			this.downAmount.addEventListener('input', function (e) {
				var value = parseFloat(e.target.value) || 0;
				value = Math.min(value, self.propertyPrice);
				self.currentDownPayment = value;
				self.currentDownPercent = self.propertyPrice > 0
					? ((self.currentDownPayment / self.propertyPrice) * 100).toFixed(1)
					: 0;
				if (self.downPercent) {
					self.downPercent.textContent = self.currentDownPercent + '%';
				}
				if (self.downSlider) {
					self.downSlider.value = self.currentDownPercent;
				}
				self.calculate();
			});
		}

		if (this.downSlider) {
			this.downSlider.addEventListener('input', function (e) {
				self.currentDownPercent = parseFloat(e.target.value) || 0;
				self.currentDownPayment = self.propertyPrice > 0
					? Math.round(self.propertyPrice * (self.currentDownPercent / 100))
					: 0;
				if (self.downPercent) {
					self.downPercent.textContent = self.currentDownPercent + '%';
				}
				if (self.downAmount) {
					self.downAmount.value = self.currentDownPayment;
				}
				self.calculate();
			});
		}

		this.termBtns.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				var target = e.currentTarget;
				self.termBtns.forEach(function (b) {
					b.classList.remove('active');
				});
				target.classList.add('active');
				self.currentTerm = parseInt(target.getAttribute('data-term'), 10) || 30;
				self.calculate();
			});
		});

		if (this.interestRate) {
			this.interestRate.addEventListener('input', function (e) {
				self.currentInterestRate = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}

		if (this.propertyTax) {
			this.propertyTax.addEventListener('input', function (e) {
				self.currentPropertyTax = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}

		if (this.homeInsurance) {
			this.homeInsurance.addEventListener('input', function (e) {
				self.currentHomeInsurance = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}

		if (this.hoaFees) {
			this.hoaFees.addEventListener('input', function (e) {
				self.currentHoaFees = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}

		if (this.pmiRate) {
			this.pmiRate.addEventListener('input', function (e) {
				self.currentPmiRate = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}

		if (this.closingCosts) {
			this.closingCosts.addEventListener('input', function (e) {
				self.currentClosingCosts = parseFloat(e.target.value) || 0;
				self.calculate();
			});
		}
	};

	HvnlyMortgageCalculatorInstance.prototype.initAdvancedToggle = function () {
		var self = this;

		if (!this.toggleBtn || !this.advancedPanel) {
			return;
		}

		this.toggleBtn.addEventListener('click', function () {
			var isOpen = self.advancedPanel.classList.toggle('show');
			self.toggleBtn.classList.toggle('active', isOpen);
			self.toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

			var icon = self.toggleBtn.querySelector('svg use');
			if (icon) {
				icon.setAttribute('xlink:href', isOpen ? '#hvnly-chevron-down' : '#hvnly-chevron-right');
			}
		});
	};

	HvnlyMortgageCalculatorInstance.prototype.initAmortizationToggle = function () {
		var self = this;

		if (!this.amortizationToggle || !this.amortizationPanel) {
			return;
		}

		this.amortizationToggle.addEventListener('click', function () {
			var isVisible = self.amortizationPanel.style.display !== 'none';
			self.amortizationPanel.style.display = isVisible ? 'none' : 'block';
			self.amortizationToggle.setAttribute('aria-expanded', isVisible ? 'false' : 'true');

			var icon = self.amortizationToggle.querySelector('svg use');
			if (icon) {
				icon.setAttribute('xlink:href', isVisible ? '#hvnly-chevron-right' : '#hvnly-chevron-down');
			}

			if (!isVisible) {
				self.updateAmortizationSchedule();
			}
		});
	};

	HvnlyMortgageCalculatorInstance.prototype.initCurrencySelector = function () {
		var self = this;

		if (!this.currencySelect) {
			return;
		}

		this.currencySelect.addEventListener('change', function (e) {
			self.currencySymbol = e.target.value;
			self.updateCurrencyDisplay();
		});
	};

	HvnlyMortgageCalculatorInstance.prototype.calculate = function () {
		if (this.propertyPrice <= 0) {
			this.updateDisplay({
				monthlyPI: 0,
				monthlyTax: 0,
				monthlyInsurance: 0,
				monthlyHOA: 0,
				monthlyPMI: 0,
				totalMonthly: 0,
				loanAmount: 0,
				totalInterest: 0,
				totalPayments: 0,
				downPayment: 0,
				downPercent: 0
			});
			return;
		}

		var loanAmount = Math.max(0, this.propertyPrice - this.currentDownPayment);
		var monthlyRate = this.currentInterestRate / 100 / 12;
		var payments = this.currentTerm * 12;
		var monthlyPI = 0;

		if (monthlyRate > 0 && loanAmount > 0) {
			var factor = Math.pow(1 + monthlyRate, payments);
			monthlyPI = loanAmount * monthlyRate * factor / (factor - 1);
		} else if (loanAmount > 0 && payments > 0) {
			monthlyPI = loanAmount / payments;
		}

		var totalPayments = monthlyPI * payments;
		var totalInterest = Math.max(0, totalPayments - loanAmount);
		var monthlyTax = this.currentPropertyTax / 12;
		var monthlyInsurance = this.currentHomeInsurance / 12;
		var monthlyHOA = this.currentHoaFees;
		var monthlyPMI = 0;

		if (this.showPmi && parseFloat(this.currentDownPercent) < 20 && loanAmount > 0) {
			monthlyPMI = loanAmount * (this.currentPmiRate / 100 / 12);
		}

		var totalMonthly = monthlyPI + monthlyTax + monthlyInsurance + monthlyHOA + monthlyPMI;

		this.updateDisplay({
			monthlyPI: monthlyPI,
			monthlyTax: monthlyTax,
			monthlyInsurance: monthlyInsurance,
			monthlyHOA: monthlyHOA,
			monthlyPMI: monthlyPMI,
			totalMonthly: totalMonthly,
			loanAmount: loanAmount,
			totalInterest: totalInterest,
			totalPayments: totalPayments,
			downPayment: this.currentDownPayment,
			downPercent: this.currentDownPercent
		});
	};

	HvnlyMortgageCalculatorInstance.prototype.formatCurrency = function (val) {
		var rounded = Math.round(val);
		if (this.currencyPosition === 'left') {
			return this.currencySymbol + rounded.toLocaleString();
		}
		return rounded.toLocaleString() + this.currencySymbol;
	};

	HvnlyMortgageCalculatorInstance.prototype.updateDisplay = function (values) {
		var self = this;

		if (this.monthlyPayment) {
			this.monthlyPayment.textContent = this.formatCurrency(values.totalMonthly);
		}
		if (this.principalInterest) {
			this.principalInterest.textContent = this.formatCurrency(values.monthlyPI);
		}
		if (this.monthlyTax) {
			this.monthlyTax.textContent = this.formatCurrency(values.monthlyTax);
		}
		if (this.monthlyInsurance) {
			this.monthlyInsurance.textContent = this.formatCurrency(values.monthlyInsurance);
		}
		if (this.monthlyHOA) {
			this.monthlyHOA.textContent = this.formatCurrency(values.monthlyHOA);
		}
		if (this.monthlyPMI) {
			this.monthlyPMI.textContent = this.formatCurrency(values.monthlyPMI);
		}
		if (this.loanAmount) {
			this.loanAmount.textContent = this.formatCurrency(values.loanAmount);
		}
		if (this.displayDownPayment) {
			this.displayDownPayment.textContent = this.formatCurrency(values.downPayment) + ' (' + values.downPercent + '%)';
		}
		if (this.totalInterest) {
			this.totalInterest.textContent = this.formatCurrency(values.totalInterest);
		}
		if (this.totalPayments) {
			this.totalPayments.textContent = this.formatCurrency(values.totalPayments);
		}
		if (this.closingCostsDisplay && this.showClosingCosts) {
			this.closingCostsDisplay.textContent = this.formatCurrency(this.currentClosingCosts);
		}

		if (this.monthlyPayment) {
			var resultEl = this.monthlyPayment.closest('.hvnly-property-single__mortgage-result');
			if (resultEl) {
				resultEl.style.transform = 'scale(1.02)';
				window.setTimeout(function () {
					resultEl.style.transform = 'scale(1)';
				}, 200);
			}
		}
	};

	HvnlyMortgageCalculatorInstance.prototype.updateAmortizationSchedule = function () {
		if (!this.amortizationTable || !this.showAmortization) {
			return;
		}

		var loanAmount = Math.max(0, this.propertyPrice - this.currentDownPayment);
		var monthlyRate = this.currentInterestRate / 100 / 12;
		var payments = this.currentTerm * 12;

		if (loanAmount <= 0 || monthlyRate <= 0) {
			this.amortizationTable.innerHTML = '';
			return;
		}

		var factor = Math.pow(1 + monthlyRate, payments);
		var monthlyPI = (loanAmount * monthlyRate * factor) / (factor - 1);
		var balance = loanAmount;
		var tableHtml = '';

		for (var year = 1; year <= this.currentTerm; year++) {
			var yearlyPrincipal = 0;
			var yearlyInterest = 0;

			for (var month = 1; month <= 12; month++) {
				if (balance <= 0) {
					break;
				}

				var interestPayment = balance * monthlyRate;
				var principalPayment = monthlyPI - interestPayment;

				yearlyPrincipal += principalPayment;
				yearlyInterest += interestPayment;
				balance -= principalPayment;

				if (balance < 0) {
					balance = 0;
				}
			}

			tableHtml += '<tr><td>' + year + '</td><td>' + this.formatCurrency(yearlyPrincipal) +
				'</td><td>' + this.formatCurrency(yearlyInterest) +
				'</td><td>' + this.formatCurrency(balance) + '</td></tr>';

			if (balance <= 0) {
				break;
			}
		}

		this.amortizationTable.innerHTML = tableHtml;
	};

	HvnlyMortgageCalculatorInstance.prototype.updateCurrencyDisplay = function () {
		this.calculate();
		document.querySelectorAll('.hvnly-mortgage-calculator-root[data-widget-id="' + this.widgetId + '"] .currency-symbol').forEach(function (el) {
			el.textContent = this.currencySymbol;
		}.bind(this));
	};

	function initFromDom() {
		var roots = document.querySelectorAll('.hvnly-mortgage-calculator-root[data-hvnly-mortgage-config]');

		if (!roots.length) {
			return;
		}

		window.HvnlyMortgageCalculator = window.HvnlyMortgageCalculator || {};

		roots.forEach(function (root) {
			var widgetId = root.getAttribute('data-widget-id');
			var rawConfig = root.getAttribute('data-hvnly-mortgage-config');

			if (!widgetId || !rawConfig) {
				return;
			}

			try {
				var config = JSON.parse(rawConfig);
				window.HvnlyMortgageCalculator[widgetId] = new HvnlyMortgageCalculatorInstance(widgetId, config);
			} catch (err) {
				// Skip invalid widget configuration silently in production.
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFromDom);
	} else {
		initFromDom();
	}
})();
