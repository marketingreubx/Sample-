<?php
// Resumable crawler: saves each Notion page (public) as compact block tree in IR_PP_RAW/<id>.json
if (!defined('IR_PP_RAW')) define('IR_PP_RAW', WP_CONTENT_DIR . '/ir-prompts/raw');
function irpp_post($path, $body) {
	for ($try = 0; $try < 3; $try++) {
		$r = wp_remote_post('https://www.notion.so/api/v3/' . $path, ['timeout' => 30, 'headers' => ['content-type' => 'application/json'], 'body' => wp_json_encode($body)]);
		if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) == 200) return json_decode(wp_remote_retrieve_body($r), true);
		usleep(800000);
	}
	return null;
}
function irpp_val($rec) { if (!$rec) return null; $v = $rec['value'] ?? null; if (isset($v['value'])) $v = $v['value']; return $v; }
function irpp_page_blocks($id) {
	$blocks = []; $cursor = ['stack' => []]; $chunk = 0;
	do {
		$d = irpp_post('loadPageChunk', ['pageId' => $id, 'limit' => 100, 'cursor' => $cursor, 'chunkNumber' => $chunk, 'verticalColumns' => false]);
		if (!$d) return null;
		foreach (($d['recordMap']['block'] ?? []) as $bid => $rec) { $v = irpp_val($rec); if ($v) $blocks[$bid] = $v; }
		$cursor = $d['cursor'] ?? ['stack' => []]; $chunk++;
	} while (!empty($cursor['stack']) && $chunk < 30);
	// fetch any missing children (non-page)
	for ($round = 0; $round < 5; $round++) {
		$missing = [];
		foreach ($blocks as $b) { if (($b['type'] ?? '') === 'page' && $b['id'] !== $id) continue; foreach (($b['content'] ?? []) as $c) if (!isset($blocks[$c])) $missing[] = $c; }
		$missing = array_values(array_unique($missing));
		if (!$missing) break;
		foreach (array_chunk($missing, 80) as $grp) {
			$d = irpp_post('syncRecordValues', ['requests' => array_map(function ($m) { return ['pointer' => ['table' => 'block', 'id' => $m], 'version' => -1]; }, $grp)]);
			foreach (($d['recordMap']['block'] ?? []) as $bid => $rec) { $v = irpp_val($rec); if ($v) $blocks[$bid] = $v; }
		}
	}
	return $blocks;
}
function irpp_node($blocks, $id, $root, $depth = 0) {
	$b = $blocks[$id] ?? null; if (!$b || empty($b['alive']) && isset($b['alive'])) return null;
	$n = ['t' => $b['type']];
	if (isset($b['properties']['title'])) $n['s'] = $b['properties']['title'];
	if (isset($b['properties']['language'])) $n['lang'] = $b['properties']['language'][0][0] ?? '';
	if (isset($b['properties']['checked'])) $n['chk'] = $b['properties']['checked'][0][0] ?? '';
	if (isset($b['properties']['source'])) $n['src'] = $b['properties']['source'][0][0] ?? '';
	if (!empty($b['format']['page_icon'])) $n['icon'] = $b['format']['page_icon'];
	if ($b['type'] === 'page' && $id !== $root) { $n['id'] = $id; return $n; }
	if ($b['type'] === 'table' ) $n['cols'] = $b['format']['table_block_column_order'] ?? [];
	if ($b['type'] === 'table_row') $n['cells'] = $b['properties'] ?? [];
	if (!empty($b['content']) && $depth < 12) { $k = []; foreach ($b['content'] as $c) { $x = irpp_node($blocks, $c, $root, $depth + 1); if ($x) $k[] = $x; } if ($k) $n['k'] = $k; }
	return $n;
}
function irpp_crawl($ids, $budget = 25) {
	wp_mkdir_p(IR_PP_RAW); $t0 = microtime(true); $done = 0; $fail = [];
	foreach ($ids as $id) {
		$f = IR_PP_RAW . '/' . $id . '.json';
		if (file_exists($f)) continue;
		if (microtime(true) - $t0 > $budget) break;
		$bl = irpp_page_blocks($id);
		if (!$bl || !isset($bl[$id])) { $fail[] = $id; continue; }
		file_put_contents($f, wp_json_encode(irpp_node($bl, $id, $id)));
		$done++;
	}
	return [$done, $fail];
}
