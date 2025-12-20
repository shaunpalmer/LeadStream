/* global LeadStreamDashboard, jQuery, Chart */
(function ($) {
  'use strict';

  const LS = window.LeadStreamDashboard || {};

  // Debug: Log the data structure
  if (window.console && console.log) {
    console.log('LeadStreamDashboard data:', LS);
  }

  const $tiles = $('#ls-tiles');
  const $status = $('#ls-status');
  const $from = $('#ls-from');
  const $to = $('#ls-to');
  const $metric = $('#ls-metric');
  const $apply = $('#ls-apply');


  // init inputs
  if (LS.range) {
    $from.val(LS.range.start);
    $to.val(LS.range.end);
  }
  if (LS.metric) {
    $metric.val(LS.metric);
  }

  // Render KPI tiles
  function renderTiles(kpis) {
    $tiles.empty();
    (kpis || []).forEach(k => {
      const cls = 'ls-tile state-' + (k.state || 'green');
      const delta = k.delta_abs === 0 ? '—' :
        (k.delta_abs > 0 ? `▲ ${k.delta_abs} (${k.delta_pct}%)` :
          `▼ ${Math.abs(k.delta_abs)} (${k.delta_pct}%)`);
      const html = `
        <div class="ls-card ${cls}">
          <div class="ls-tile-label">${k.label}</div>
          <div class="ls-tile-value">${k.value}</div>
          <div class="ls-tile-delta">${delta}</div>
        </div>`;
      $tiles.append(html);
    });
  }

  // Render statuses
  function renderStatus(s) {
    $status.empty();
    const entries = Object.entries(s || {});
    entries.forEach(([key, obj]) => {
      const state = obj.state || 'green';
      const msg = obj.msg || '';
      $status.append(`
        <div class="ls-badge state-${state}">
          <span class="ls-badge-key">${key.toUpperCase()}</span>
          <span class="ls-badge-msg">${msg}</span>
        </div>
      `);
    });
  }

  // Trend chart
  const ctx = document.getElementById('ls-trend');
  let chart;
  function renderTrend(series) {
    if (!ctx) return;
    if (chart) {
      chart.destroy();
    }

    const labels = (series.labels || []).map(formatDateLabel);
    const data = series.data || [];

    chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: metricLabel($metric.val()),
          data: data,
          fill: false,
          borderColor: '#2271b1',
          backgroundColor: 'rgba(34, 113, 177, 0.1)',
          tension: 0.25,
          pointBackgroundColor: '#2271b1',
          pointBorderColor: '#2271b1',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            title: { display: true, text: 'Date' },
            grid: { color: 'rgba(0,0,0,0.1)' }
          },
          y: {
            title: { display: true, text: 'Count' },
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.1)' },
            ticks: {
              stepSize: 1
            }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleColor: '#fff',
            bodyColor: '#fff'
          }
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  }

  function formatDateLabel(dateStr) {
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    } catch (e) {
      return dateStr;
    }
  } function metricLabel(key) {
    switch (key) {
      case 'forms': return 'Form Submits';
      case 'leads': return 'Total Leads';
      case 'calls':
      default: return 'Phone Calls';
    }
  }

  // Initial render
  renderTiles(LS.kpis || []);
  renderTrend(LS.trend || { labels: [], data: [] });
  renderStatus(LS.status || {});

  // Apply (Phase 1: submit as GET reload; Phase 2: AJAX/REST)
  $apply.on('click', function () {
    const from = $from.val();
    const to = $to.val();
    const metric = $metric.val();
    const url = new URL(window.location.href);
    url.searchParams.set('from', from);
    url.searchParams.set('to', to);
    url.searchParams.set('metric', metric);
    window.location.href = url.toString();
  });

})(jQuery);
