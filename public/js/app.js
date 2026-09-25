/* Arowin — Yield estimator */
(function () {
      var PAIR_RATE = 5;
      var PAIR_CAP = 50;
      var leftEl = document.getElementById("leftStarters");
      var rightEl = document.getElementById("rightStarters");
      var leftValue = document.getElementById("leftValue");
      var rightValue = document.getElementById("rightValue");
      var pairsEl = document.getElementById("pairsMatched");
      var sponsorEl = document.getElementById("sponsorComm");
      var profitEl = document.getElementById("pairingProfit");
      var costLabel = document.getElementById("costLabel");
      var costBtns = document.querySelectorAll(".cost-btn");
      if (!leftEl || !rightEl) return;

      var baseCost = 750;

      function money(n) {
        return "$" + n.toFixed(2);
      }

      function updateMetrics() {
        var left = Number(leftEl.value);
        var right = Number(rightEl.value);
        leftValue.textContent = left;
        rightValue.textContent = right;
        leftEl.setAttribute("aria-valuenow", String(left));
        rightEl.setAttribute("aria-valuenow", String(right));

        var pairs = Math.min(left, right, PAIR_CAP);
        var profit = pairs * PAIR_RATE;
        // Starter direct referral sample: 5% of $100 equivalent = $5 at default;
        // scale lightly with node tier while keeping screenshot default at $750 → $5.00
        var sponsor = baseCost * 0.05 * (100 / 750);

        pairsEl.textContent = String(pairs);
        sponsorEl.textContent = money(sponsor);
        profitEl.textContent = money(profit);
      }

      leftEl.addEventListener("input", updateMetrics);
      rightEl.addEventListener("input", updateMetrics);

      costBtns.forEach(function (btn) {
        btn.addEventListener("click", function () {
          costBtns.forEach(function (b) { b.classList.remove("active"); });
          btn.classList.add("active");
          baseCost = Number(btn.getAttribute("data-cost"));
          costLabel.textContent = "$" + baseCost + " USDT";
          updateMetrics();
        });
      });

      updateMetrics();
    })();
