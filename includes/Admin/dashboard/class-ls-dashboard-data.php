<?php
namespace LeadStream\Admin\Dashboard;

class Data {
	private string $tbl_clicks;     // SINGLE SOURCE: all events (phone + link + form)
	private string $tbl_links;      // link definitions (Pretty Links)
	private string $tbl_events;     // optional: GA4-style event stream (supplementary)

	public function __construct() {
		global $wpdb;
		$p = $wpdb->prefix;
		$this->tbl_clicks = $p . 'ls_clicks';   // ONE TABLE FOR ALL CLICK EVENTS
		$this->tbl_links  = $p . 'ls_links';    // LINK DEFINITIONS
		$this->tbl_events = $p . 'ls_events';   // OPTIONAL EVENT LOG
	}

	/** -------------------- PUBLIC KPI API -------------------- */
	public function calls_today(): int { return $this->count_calls_between($this->today(), $this->today()); }
	public function calls_week(): int  { [$s,$e] = $this->lastNDays(7); return $this->count_calls_between($s, $e); }
	public function calls_month(): int { [$s,$e] = $this->lastNDays(30); return $this->count_calls_between($s, $e); }
	public function forms_today(): int { return $this->count_forms_between($this->today(), $this->today()); }
	public function links_today(): int { return $this->count_links_between($this->today(), $this->today()); }
	public function links_week(): int  { [$s,$e] = $this->lastNDays(7); return $this->count_links_between($s, $e); }
	public function links_month(): int { [$s,$e] = $this->lastNDays(30); return $this->count_links_between($s, $e); }

	// Compatibility wrapper expected by existing render code
	public function kpis(): array {
		// Compute simple deltas: compare today to average per-day this week (excluding today)
		$calls_today = (int)$this->calls_today();
		$calls_week = (int)$this->calls_week();
		$calls_month = (int)$this->calls_month();
		$forms_today = (int)$this->forms_today();
		$links_today = (int)$this->links_today();
		$links_week = (int)$this->links_week();
		$links_month = (int)$this->links_month();

		$days_in_week = 7;
		$avg_per_day = $days_in_week > 1 ? floor($calls_week / $days_in_week) : 0;
		$delta_abs_calls = $calls_today - $avg_per_day;
		$delta_pct_calls = $avg_per_day > 0 ? round(($delta_abs_calls / max(1, $avg_per_day)) * 100, 1) : ($delta_abs_calls > 0 ? 100.0 : 0.0);

		// Links deltas
		$links_avg = $days_in_week > 1 ? floor($links_week / $days_in_week) : 0;
		$delta_abs_links = $links_today - $links_avg;
		$delta_pct_links = $links_avg > 0 ? round(($delta_abs_links / max(1, $links_avg)) * 100, 1) : ($delta_abs_links > 0 ? 100.0 : 0.0);

		// Simple forms delta vs week average
		$forms_avg = $days_in_week > 1 ? floor($forms_today) : 0; // conservative
		$delta_abs_forms = $forms_today - $forms_avg;
		$delta_pct_forms = $forms_avg > 0 ? round(($delta_abs_forms / max(1, $forms_avg)) * 100, 1) : ($delta_abs_forms > 0 ? 100.0 : 0.0);

		return [
			'calls_today' => ['value' => $calls_today, 'delta_abs' => $delta_abs_calls, 'delta_pct' => $delta_pct_calls],
			'calls_week'  => ['value' => $calls_week, 'delta_abs' => 0, 'delta_pct' => 0.0],
			'calls_month' => ['value' => $calls_month, 'delta_abs' => 0, 'delta_pct' => 0.0],
			'forms_today' => ['value' => $forms_today, 'delta_abs' => $delta_abs_forms, 'delta_pct' => $delta_pct_forms],
			'links_today' => ['value' => $links_today, 'delta_abs' => $delta_abs_links, 'delta_pct' => $delta_pct_links],
			'links_week'  => ['value' => $links_week, 'delta_abs' => 0, 'delta_pct' => 0.0],
			'links_month' => ['value' => $links_month, 'delta_abs' => 0, 'delta_pct' => 0.0],
		];
	}

	/**
	 * Timeseries for chart.
	 * $metric: 'calls' | 'forms' | 'leads'
	 * Returns: ['labels'=>['YYYY-MM-DD',...], 'data'=>[int,...]]
	 */
	public function get_timeseries(array $range, string $metric): array {
		$calls = $this->series_calls($range);
		if ($metric === 'calls') return $calls;

		$forms = $this->series_forms($range);
		if ($metric === 'forms') return $forms;

		// leads = calls + forms
		$labels = $calls['labels'];
		$sum = [];
		foreach ($labels as $i => $d) {
			$sum[] = (int)($calls['data'][$i] ?? 0) + (int)($forms['data'][$i] ?? 0);
		}
		return ['labels'=>$labels, 'data'=>$sum];
	}

	/** -------------------- PRIVATE COUNTS -------------------- */
	private function count_calls_between(string $start, string $end): int {
		// NORMALIZED: Use ls_clicks with event filtering
		$n = $this->count_from_clicks($start, $end, 'phone');
		if ($n !== null) return $n;

		// Fallback: generic ls_events (event_type=phone_click)
		return $this->count_from_events('phone_click', $start, $end);
	}

	private function count_forms_between(string $start, string $end): int {
		// NORMALIZED: Use ls_clicks with form event filtering
		$n = $this->count_from_clicks($start, $end, 'form');
		if ($n !== null) return $n;

		// Fallback: generic ls_events (event_type=form_submit)
		return $this->count_from_events('form_submit', $start, $end);
	}

	private function count_links_between(string $start, string $end): int {
		// NORMALIZED: Use ls_clicks with link filtering
		$n = $this->count_from_clicks($start, $end, 'link');
		if ($n !== null) return $n;

		// Fallback: generic ls_events (event_type=link_click)
		return $this->count_from_events('link_click', $start, $end);
	}

	/** -------------------- PRIVATE SERIES -------------------- */
	private function series_calls(array $r): array {
		// NORMALIZED: From ls_clicks (phone events only)
		$rows = $this->series_from_clicks($r, 'phone');
		if ($rows !== null) return $this->fill_dates($r, $rows);

		// Fallback: From ls_events
		$rows = $this->series_from_events('phone_click', $r);
		return $this->fill_dates($r, $rows);
	}

	private function series_forms(array $r): array {
		// NORMALIZED: From ls_clicks (form events only)
		$rows = $this->series_from_clicks($r, 'form');
		if ($rows !== null) return $this->fill_dates($r, $rows);

		// Fallback: From ls_events
		$rows = $this->series_from_events('form_submit', $r);
		return $this->fill_dates($r, $rows);
	}

	/** -------------------- DB helpers -------------------- */
	private function has_table(string $table): bool {
		global $wpdb;
		static $cache = [];
		if (isset($cache[$table])) return $cache[$table];
		$cache[$table] = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return $cache[$table];
	}

	private function count_from_clicks(string $start, string $end, string $type): ?int {
		global $wpdb;
		if (!$this->has_table($this->tbl_clicks)) return null;
		
		// NORMALIZED: Use only clicked_at timestamp, null-safe
		$sql = "SELECT COUNT(*) FROM {$this->tbl_clicks}
				WHERE COALESCE(link_type, '') = %s 
				AND DATE(COALESCE(clicked_at, '1970-01-01')) BETWEEN %s AND %s";
		return (int) $wpdb->get_var($wpdb->prepare($sql, $type, $start, $end));
	}

	private function count_from_table(string $table, string $dateCol, string $start, string $end): ?int {
		global $wpdb;
		if (!$this->has_table($table)) return null;
		
		// NULL-SAFE: Handle missing date columns gracefully
		$sql = "SELECT COUNT(*) FROM {$table} 
				WHERE DATE(COALESCE({$dateCol}, '1970-01-01')) BETWEEN %s AND %s";
		return (int) $wpdb->get_var($wpdb->prepare($sql, $start, $end));
	}

	private function count_from_events(string $eventType, string $start, string $end): int {
		global $wpdb;
		if (!$this->has_table($this->tbl_events)) return 0;
		
		// NULL-SAFE: Handle missing event_type and created_at
		$sql = "SELECT COUNT(*) FROM {$this->tbl_events}
				WHERE COALESCE(event_type, '') = %s 
				AND DATE(COALESCE(created_at, '1970-01-01')) BETWEEN %s AND %s";
		return (int) $wpdb->get_var($wpdb->prepare($sql, $eventType, $start, $end));
	}

	private function series_from_clicks(array $r, string $type): ?array {
		global $wpdb;
		if (!$this->has_table($this->tbl_clicks)) return null;
		
		// NORMALIZED: Use only clicked_at, null-safe
		$sql = "SELECT DATE(COALESCE(clicked_at, '1970-01-01')) d, COUNT(*) c
				FROM {$this->tbl_clicks}
				WHERE COALESCE(link_type, '') = %s 
				AND DATE(COALESCE(clicked_at, '1970-01-01')) BETWEEN %s AND %s
				GROUP BY DATE(COALESCE(clicked_at, '1970-01-01')) ORDER BY d ASC";
		return $wpdb->get_results($wpdb->prepare($sql, $type, $r['start'], $r['end']), ARRAY_A);
	}

	private function series_from_events(string $eventType, array $r): array {
		global $wpdb;
		if (!$this->has_table($this->tbl_events)) return [];
		
		// NULL-SAFE: Handle missing event_type and created_at
		$sql = "SELECT DATE(COALESCE(created_at, '1970-01-01')) d, COUNT(*) c
				FROM {$this->tbl_events}
				WHERE COALESCE(event_type, '') = %s 
				AND DATE(COALESCE(created_at, '1970-01-01')) BETWEEN %s AND %s
				GROUP BY DATE(COALESCE(created_at, '1970-01-01')) ORDER BY d ASC";
		return $wpdb->get_results($wpdb->prepare($sql, $eventType, $r['start'], $r['end']), ARRAY_A);
	}

	/** -------------------- utilities -------------------- */
	private function fill_dates(array $r, array $rows): array {
		$map = [];
		foreach ($rows as $row) { $map[$row['d']] = (int)$row['c']; }
		$labels = []; $data = [];
		$cur = new \DateTimeImmutable($r['start']);
		$end = new \DateTimeImmutable($r['end']);
		while ($cur <= $end) {
			$d = $cur->format('Y-m-d');
			$labels[] = $d; $data[] = $map[$d] ?? 0;
			$cur = $cur->modify('+1 day');
		}
		return ['labels'=>$labels, 'data'=>$data];
	}

	private function today(): string {
		return (new \DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');
	}
	private function lastNDays(int $n): array {
		$end = $this->today();
		$start = (new \DateTimeImmutable($end))->modify('-'.($n-1).' days')->format('Y-m-d');
		return [$start, $end];
	}
}