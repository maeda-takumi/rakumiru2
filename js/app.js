(() => {
  const qs = (sel, el = document) => el.querySelector(sel);
  const qsa = (sel, el = document) => [...el.querySelectorAll(sel)];

  const API = (action, params = {}, method = "GET") => {
    const url = new URL(location.href);
    url.searchParams.set("api", "1");
    url.searchParams.set("action", action);

    if (method === "GET") {
      Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
      return fetch(url.toString(), { method: "GET" }).then(r => r.json());
    }

    const body = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => body.set(k, v));
    return fetch(url.toString(), {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body,
    }).then(r => r.json());
  };

  const elTree = qs("#js-tree");
  const elSearch = qs("#js-search");
  const elSearchClear = qs("#js-search-clear");
  const elActiveOnly = qs("#js-active-only");
  const elRefresh = qs("#js-refresh");

  const elGenreDetail = qs("#js-genre-detail");
  const elGenreSub = qs("#js-genre-sub");
  const elCrumbs = qs("#js-crumbs");

  const elJobState = qs("#js-job-state");
  const elJobPill = qs("#js-job-pill");

  const tabButtons = qsa(".tab");
  const tabRank = qs("#js-tab-rank");
  const tabStats = qs("#js-tab-stats");

  let state = {
    selectedGenreId: null,
    expanded: new Set(),
    cacheChildren: new Map(), // parent_id -> children
    search: "",
    activeOnly: false,
    currentTab: "rank",
  };

  const esc = (s) =>
    String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const fmtDT = (s) => (s ? esc(s).replace("T", " ") : "—");
  const fmtNum = (n) => (n === null || n === undefined ? "—" : Number(n).toLocaleString());
  const fmtPct = (n) => (n === null || n === undefined ? "—" : `${n}%`);

  // -------------------------
  // Tabs
  // -------------------------
  const setTab = (tab) => {
    state.currentTab = tab;
    tabButtons.forEach((b) => b.classList.toggle("is-active", b.dataset.tab === tab));
    tabRank.classList.toggle("is-hidden", tab !== "rank");
    tabStats.classList.toggle("is-hidden", tab !== "stats");
  };

  tabButtons.forEach((b) => {
    b.addEventListener("click", () => {
      setTab(b.dataset.tab);
      if (state.selectedGenreId) loadPanels(state.selectedGenreId);
    });
  });

  // -------------------------
  // Tree rendering
  // -------------------------
  const renderTreeNode = (g, level) => {
    const hasChildren = true; // 子が0でもUIは展開ボタン出す（未ロードなので）
    const expanded = state.expanded.has(g.genre_id);

    const badge = g.is_active == 1 ? `<span class="badge badge--ok">active</span>` : `<span class="badge">off</span>`;
    const caret = hasChildren
      ? `<button class="caret ${expanded ? "is-open" : ""}" data-act="toggle" data-id="${g.genre_id}" title="展開"></button>`
      : `<span class="caret caret--spacer"></span>`;

    return `
      <div class="node" style="padding-left:${8 + level * 14}px" data-id="${g.genre_id}">
        ${caret}
        <button class="node__btn" data-act="select" data-id="${g.genre_id}">
          <span class="node__name">${esc(g.genre_name)}</span>
          <span class="node__meta">#${esc(g.genre_id)} / d${esc(g.depth)}</span>
        </button>
        <div class="node__right">${badge}</div>
      </div>
      <div class="children" data-children-of="${g.genre_id}" ${expanded ? "" : 'style="display:none"'}></div>
    `;
  };

  const loadChildren = async (parentId) => {
    const key = parentId === null ? "null" : String(parentId);
    if (state.cacheChildren.has(key)) return state.cacheChildren.get(key);

    const res = await API("children", { parent_id: key });
    if (!res.ok) throw new Error(res.message || "children load failed");
    state.cacheChildren.set(key, res.children || []);
    return res.children || [];
  };

  const buildTree = async () => {
    elTree.innerHTML = `<div class="muted">読み込み中…</div>`;

    // 検索がある場合は「一覧検索結果」を出す
    if (state.search) {
      const res = await API("list_genres", { q: state.search, active_only: state.activeOnly ? "1" : "0" });
      if (!res.ok) {
        elTree.innerHTML = `<div class="error">${esc(res.message || "error")}</div>`;
        return;
      }

      const rows = res.genres || [];
      elTree.innerHTML = `
        <div class="hint">検索結果: ${rows.length} 件</div>
        <div class="list">
          ${rows
            .slice(0, 400)
            .map(
              (g) => `
              <div class="row">
                <button class="row__btn" data-act="select" data-id="${g.genre_id}">
                  <div class="row__title">${esc(g.genre_name)}</div>
                  <div class="row__sub">#${esc(g.genre_id)} / parent:${g.parent_genre_id ?? "null"} / depth:${esc(g.depth)}</div>
                </button>
                <div class="row__right">${g.is_active == 1 ? `<span class="badge badge--ok">active</span>` : `<span class="badge">off</span>`}</div>
              </div>
            `
            )
            .join("")}
        </div>
      `;
      return;
    }

    // 通常はツリー
    try {
      const roots = await loadChildren(null);

      elTree.innerHTML = `
        <div class="hint">ツリーを展開してジャンルを選択</div>
        <div class="tree__body">
          ${roots.map((g) => renderTreeNode(g, 0)).join("")}
        </div>
      `;
    } catch (e) {
      elTree.innerHTML = `<div class="error">${esc(e.message)}</div>`;
    }
  };

  const toggleNode = async (id) => {
    const gid = Number(id);
    const expanded = state.expanded.has(gid);
    const holder = qs(`[data-children-of="${gid}"]`, elTree);
    if (!holder) return;

    if (expanded) {
      state.expanded.delete(gid);
      holder.style.display = "none";
      const caret = qs(`.caret[data-id="${gid}"]`, elTree);
      caret?.classList.remove("is-open");
      return;
    }

    state.expanded.add(gid);
    holder.style.display = "";

    const caret = qs(`.caret[data-id="${gid}"]`, elTree);
    caret?.classList.add("is-open");

    // childrenを埋める
    const children = await loadChildren(gid);
    holder.innerHTML = children.map((g) => renderTreeNode(g, (g.depth ?? 1))).join("");
  };

  // -------------------------
  // Panels
  // -------------------------
  const renderGenreDetail = (genre, childCount) => {
    const isActive = genre.is_active == 1;

    return `
      <div class="kv">
        <div class="kv__row"><div class="kv__k">genre_id</div><div class="kv__v">#${esc(genre.genre_id)}</div></div>
        <div class="kv__row"><div class="kv__k">name</div><div class="kv__v">${esc(genre.genre_name)}</div></div>
        <div class="kv__row"><div class="kv__k">parent</div><div class="kv__v">${genre.parent_genre_id ?? "null"}</div></div>
        <div class="kv__row"><div class="kv__k">depth</div><div class="kv__v">${esc(genre.depth)}</div></div>
        <div class="kv__row"><div class="kv__k">children</div><div class="kv__v">${fmtNum(childCount)}</div></div>
        <div class="kv__row"><div class="kv__k">updated</div><div class="kv__v">${fmtDT(genre.updated_at)}</div></div>
      </div>

      <div class="divider"></div>

      <div class="inline-actions">
        <button class="btn ${isActive ? "btn--danger" : ""}" id="js-toggle-active">
          ${isActive ? "非アクティブにする" : "アクティブにする"}
        </button>
        <a class="btn btn--ghost" href="#" id="js-open-children">子ジャンルを展開</a>
      </div>

      <div class="note">
        ※ is_active は genres テーブルのフラグを更新します。
      </div>
    `;
  };

  const renderRankTable = (capturedDate, rows) => {
    if (!capturedDate) return `<div class="muted">このジャンルの rank_daily がまだありません</div>`;

    const tr = rows
      .map((r) => {
        const img = r.image_url ? `<img class="thumb" src="${esc(r.image_url)}" alt="">` : `<div class="thumb thumb--empty"></div>`;
        const name = r.item_name ? esc(r.item_name) : `<span class="muted">(未登録)</span>`;
        const link = r.item_url ? `<a class="link" href="${esc(r.item_url)}" target="_blank" rel="noopener">${name}</a>` : name;

        return `
          <tr>
            <td class="td-num">${esc(r.rank_pos)}</td>
            <td class="td-item">
              ${img}
              <div class="td-item__meta">
                <div class="td-item__name">${link}</div>
                <div class="td-item__sub">${esc(r.item_code)} / ${esc(r.shop_name ?? "")}</div>
              </div>
            </td>
            <td class="td-num">¥${fmtNum(r.price)}</td>
            <td class="td-num">${fmtNum(r.review_count)}</td>
            <td class="td-num">${fmtPct(r.point_rate)}</td>
            <td>${fmtDT(r.last_seen_at)}</td>
          </tr>
        `;
      })
      .join("");

    return `
      <div class="table-head">
        <div class="table-head__left">
          <div class="table-title">captured_date: <span class="accent">${esc(capturedDate)}</span></div>
          <div class="muted">最大200件</div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="th-num">#</th>
              <th>商品</th>
              <th class="th-num">価格</th>
              <th class="th-num">レビュー</th>
              <th class="th-num">Pt%</th>
              <th>items.last_seen_at</th>
            </tr>
          </thead>
          <tbody>${tr || `<tr><td colspan="6" class="muted">データなし</td></tr>`}</tbody>
        </table>
      </div>
    `;
  };

  const renderStatsTable = (rows) => {
    if (!rows?.length) return `<div class="muted">このジャンルの rank_stats_30d がまだありません</div>`;

    const tr = rows
      .map((r) => {
        const img = r.image_url ? `<img class="thumb" src="${esc(r.image_url)}" alt="">` : `<div class="thumb thumb--empty"></div>`;
        const name = r.item_name ? esc(r.item_name) : `<span class="muted">(未登録)</span>`;
        const link = r.item_url ? `<a class="link" href="${esc(r.item_url)}" target="_blank" rel="noopener">${name}</a>` : name;

        return `
          <tr>
            <td class="td-item">
              ${img}
              <div class="td-item__meta">
                <div class="td-item__name">${link}</div>
                <div class="td-item__sub">${esc(r.item_code)} / ${esc(r.shop_name ?? "")}</div>
              </div>
            </td>
            <td class="td-num">${fmtNum(r.appear_days_30d)}</td>
            <td class="td-num">${fmtNum(r.best_rank_30d)}</td>
            <td class="td-num">${r.avg_rank_30d ?? "—"}</td>
            <td>${esc(r.last_seen_date ?? "—")}</td>
            <td class="td-num">${fmtNum(r.last_rank)}</td>
          </tr>
        `;
      })
      .join("");

    return `
      <div class="table-head">
        <div class="table-head__left">
          <div class="table-title">30日統計</div>
          <div class="muted">最大300件</div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>商品</th>
              <th class="th-num">出現日数</th>
              <th class="th-num">最高</th>
              <th class="th-num">平均</th>
              <th>最終日</th>
              <th class="th-num">最終順位</th>
            </tr>
          </thead>
          <tbody>${tr}</tbody>
        </table>
      </div>
    `;
  };

  const loadGenre = async (gid) => {
    const res = await API("get_genre", { genre_id: gid });
    if (!res.ok) throw new Error(res.message || "genre load failed");
    return res;
  };

  const loadPanels = async (gid) => {
    // detail
    elGenreDetail.innerHTML = `<div class="muted">読み込み中…</div>`;
    tabRank.innerHTML = `<div class="muted">読み込み中…</div>`;
    tabStats.innerHTML = `<div class="muted">読み込み中…</div>`;

    try {
      const detail = await loadGenre(gid);
      const g = detail.genre;

      elCrumbs.textContent = `${g.genre_name}  (#${g.genre_id})`;
      elGenreSub.textContent = `parent: ${g.parent_genre_id ?? "null"} / depth: ${g.depth}`;

      elGenreDetail.innerHTML = renderGenreDetail(g, detail.child_count || 0);

      // active toggle
      const btnToggle = qs("#js-toggle-active");
      btnToggle?.addEventListener("click", async () => {
        btnToggle.disabled = true;
        const next = g.is_active == 1 ? 0 : 1;
        const r = await API("set_active", { genre_id: g.genre_id, is_active: next }, "POST");
        if (!r.ok) {
          alert(r.message || "更新失敗");
          btnToggle.disabled = false;
          return;
        }
        // キャッシュを捨てて再読込
        state.cacheChildren.clear();
        await buildTree();
        await loadPanels(gid);
      });

      // 子ジャンル展開（ツリー上で）
      qs("#js-open-children")?.addEventListener("click", async (ev) => {
        ev.preventDefault();
        if (!state.search) {
          // ツリーで該当ノードが見えない可能性もあるが、とりあえず expand を試みる
          await toggleNode(g.genre_id);
        }
      });

      // ranking / stats
      if (state.currentTab === "rank") {
        const rr = await API("latest_rankings", { genre_id: gid });
        tabRank.innerHTML = rr.ok ? renderRankTable(rr.captured_date, rr.rows || []) : `<div class="error">${esc(rr.message)}</div>`;
      } else {
        const ss = await API("stats_30d", { genre_id: gid });
        tabStats.innerHTML = ss.ok ? renderStatsTable(ss.rows || []) : `<div class="error">${esc(ss.message)}</div>`;
      }

      // もう片方のタブも軽く裏で更新（体感よくする）
      if (state.currentTab === "rank") {
        API("stats_30d", { genre_id: gid }).then((ss) => {
          if (ss.ok) tabStats.innerHTML = renderStatsTable(ss.rows || []);
        });
      } else {
        API("latest_rankings", { genre_id: gid }).then((rr) => {
          if (rr.ok) tabRank.innerHTML = renderRankTable(rr.captured_date, rr.rows || []);
        });
      }
    } catch (e) {
      elGenreDetail.innerHTML = `<div class="error">${esc(e.message)}</div>`;
      tabRank.innerHTML = `<div class="error">${esc(e.message)}</div>`;
      tabStats.innerHTML = `<div class="error">${esc(e.message)}</div>`;
    }
  };

  // -------------------------
  // job_state
  // -------------------------
  const renderJobs = (jobs) => {
    if (!jobs?.length) return `<div class="muted">job_state が空です</div>`;

    const rows = jobs
      .map((j) => {
        const st = String(j.status || "idle");
        const pill =
          st === "ok" ? `<span class="badge badge--ok">ok</span>` :
          st === "running" ? `<span class="badge badge--warn">running</span>` :
          st === "error" ? `<span class="badge badge--danger">error</span>` :
          `<span class="badge">idle</span>`;

        return `
          <div class="jobrow">
            <div class="jobrow__left">
              <div class="jobrow__name">${esc(j.job_name)}</div>
              <div class="jobrow__sub">last_run_at: ${fmtDT(j.last_run_at)} / last_run_date: ${esc(j.last_run_date ?? "—")}</div>
              ${j.message ? `<div class="jobrow__msg">${esc(j.message)}</div>` : ""}
            </div>
            <div class="jobrow__right">
              ${pill}
            </div>
          </div>
        `;
      })
      .join("");

    return `<div class="joblist">${rows}</div>`;
  };

  const loadJobs = async () => {
    const res = await API("job_state");
    if (!res.ok) {
      elJobState.innerHTML = `<div class="error">${esc(res.message || "job_state error")}</div>`;
      elJobPill.textContent = "job: error";
      return;
    }
    elJobState.innerHTML = renderJobs(res.jobs || []);

    // ピルは “error” が一つでもあれば error 表示
    const jobs = res.jobs || [];
    const hasError = jobs.some(j => j.status === "error");
    const hasRunning = jobs.some(j => j.status === "running");
    const label = hasError ? "error" : hasRunning ? "running" : "ok";
    elJobPill.textContent = `job: ${label}`;
    elJobPill.classList.toggle("pill--danger", hasError);
    elJobPill.classList.toggle("pill--warn", !hasError && hasRunning);
  };

  // -------------------------
  // Events
  // -------------------------
  elTree?.addEventListener("click", async (ev) => {
    const t = ev.target;
    const act = t?.dataset?.act;
    const id = t?.dataset?.id;

    if (act === "toggle" && id) {
      ev.preventDefault();
      try {
        await toggleNode(id);
      } catch (e) {
        alert(e.message);
      }
      return;
    }

    if (act === "select" && id) {
      ev.preventDefault();
      const gid = Number(id);
      state.selectedGenreId = gid;
      await loadPanels(gid);
      return;
    }
  });

  const applySearch = async () => {
    state.search = elSearch.value.trim();
    await buildTree();
  };

  elSearch?.addEventListener("input", () => {
    // 軽いデバウンス
    if (elSearch._t) clearTimeout(elSearch._t);
    elSearch._t = setTimeout(applySearch, 250);
  });

  elSearchClear?.addEventListener("click", async () => {
    elSearch.value = "";
    state.search = "";
    await buildTree();
  });

  elActiveOnly?.addEventListener("change", async () => {
    state.activeOnly = !!elActiveOnly.checked;
    state.cacheChildren.clear();
    await buildTree();
  });

  elRefresh?.addEventListener("click", async () => {
    state.cacheChildren.clear();
    await buildTree();
    if (state.selectedGenreId) await loadPanels(state.selectedGenreId);
    await loadJobs();
  });

  // -------------------------
  // init
  // -------------------------
  const init = async () => {
    setTab("rank");
    await buildTree();
    await loadJobs();
    // 30秒おきにjob更新（軽い）
    setInterval(loadJobs, 30000);
  };

  init().catch((e) => {
    elTree.innerHTML = `<div class="error">${esc(e.message)}</div>`;
  });
})();
