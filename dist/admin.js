(function () {
  var config = window.agentkitAdmin;
  var root = document.getElementById("agentkit-admin-root");
  if (!config || !root) {
    return;
  }

  var state = {
    settings: JSON.parse(JSON.stringify(config.settings || {})),
    stats: null,
    files: [],
    filesPollingTimer: null,
    upload: {
      status: "idle",
      message: "",
      processed: 0,
      total: 0,
      failures: 0,
    },
    selectedFiles: [],
    conversations: [],
    activeTab: "dashboard",
  };

  var providerCatalog = {
    openai: {
      chat: ["gpt-5.4", "gpt-5.4-mini", "gpt-5.4-nano"],
      embedding: ["text-embedding-3-small", "text-embedding-3-large"],
    },
    anthropic: {
      chat: ["claude-opus-4-6", "claude-sonnet-4-6", "claude-haiku-4-5"],
      embedding: ["none"],
    },
    gemini: {
      chat: [
        "gemini-3-flash-preview",
        "gemini-3-pro-preview",
        "gemini-2.5-pro",
        "gemini-2.5-flash",
        "gemini-2.5-flash-lite",
      ],
      embedding: ["gemini-embedding-2-preview", "gemini-embedding-001"],
    },
    openrouter: {
      chat: [
        "openai/gpt-5.2",
        "anthropic/claude-sonnet-4-6",
        "google/gemini-2.5-pro",
        "google/gemini-3-flash-preview",
      ],
      embedding: [
        "openai/text-embedding-3-small",
        "google/gemini-embedding-2-preview",
      ],
    },
  };

  function api(path, options) {
    return fetch(
      config.restUrl + path,
      Object.assign(
        {
          headers: {
            "X-WP-Nonce": config.nonce,
            "Content-Type": "application/json",
          },
        },
        options || {},
      ),
    ).then(function (response) {
      return response.json();
    });
  }

  function apiForm(path, formData) {
    return fetch(config.restUrl + path, {
      method: "POST",
      headers: {
        "X-WP-Nonce": config.nonce,
      },
      body: formData,
    }).then(function (response) {
      return response
        .json()
        .catch(function () {
          return { message: "Respuesta inválida del servidor." };
        })
        .then(function (payload) {
          if (!response.ok || (payload && payload.code)) {
            throw new Error(
              (payload && payload.message) || "No se pudo subir el archivo.",
            );
          }

          return payload || {};
        });
    });
  }

  function render() {
    root.innerHTML =
      "" +
      '<div class="agentkit-admin">' +
      '<div class="agentkit-admin-shell">' +
      '<aside class="agentkit-sidebar">' +
      renderNav() +
      "</aside>" +
      '<main class="agentkit-content">' +
      renderPanel() +
      "</main>" +
      "</div>" +
      "</div>";

    bindEvents();
  }

  function renderNav() {
    var tabs = [
      ["dashboard", "Dashboard"],
      ["general", "General"],
      ["models", "Models"],
      ["security", "Security"],
      ["knowledge", "Knowledge Base"],
      ["conversations", "Conversations"],
    ];

    return (
      '<h1>AgentKit</h1><nav class="agentkit-nav">' +
      tabs
        .map(function (tab) {
          return (
            '<button type="button" class="agentkit-nav-btn' +
            (state.activeTab === tab[0] ? " is-active" : "") +
            '" data-tab="' +
            tab[0] +
            '">' +
            tab[1] +
            "</button>"
          );
        })
        .join("") +
      "</nav>"
    );
  }

  function renderPanel() {
    if (state.activeTab === "dashboard") return renderDashboard();
    if (state.activeTab === "general") return renderGeneral();
    if (state.activeTab === "models") return renderModels();
    if (state.activeTab === "security") return renderSecurity();
    if (state.activeTab === "knowledge") return renderKnowledge();
    return renderConversations();
  }

  function renderDashboard() {
    var stats = state.stats || {};
    var series = stats.dailySeries || [];
    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar">' +
      "<h2>Dashboard</h2>" +
      '<button type="button" data-action="refresh-dashboard">Actualizar</button>' +
      "</div>" +
      '<div class="agentkit-cards">' +
      card("Conversaciones", stats.conversations || 0) +
      card("Mensajes", stats.messages || 0) +
      card("Chunks", stats.chunks || 0) +
      card("Archivos", stats.files || 0) +
      card("Archivos indexados", stats.indexedFiles || 0) +
      card("Errores de archivo", stats.errorFiles || 0) +
      card("Sesiones únicas", lastSeriesValue(series, "unique_sessions")) +
      card(
        "Promedio msgs/conv",
        lastSeriesValue(series, "avg_messages_per_conv"),
      ) +
      "</div>" +
      '<div class="agentkit-dashboard-grid">' +
      renderLineChart(
        "Conversaciones por día",
        series,
        "conversations",
        "#2563eb",
      ) +
      renderLineChart("Mensajes por día", series, "messages", "#0f766e") +
      renderBarChart(
        "Estado de archivos",
        stats.fileStatuses || [],
        "status",
        "total",
      ) +
      renderTopQuestions(stats.topQuestions || []) +
      "</div>" +
      "</section>"
    );
  }

  function renderGeneral() {
    var general = state.settings.general || {};
    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar"><h2>General</h2><button type="button" data-action="save-settings">Guardar</button></div>' +
      field(
        "Nombre del agente",
        '<input data-setting="general.agent_name" value="' +
          escapeAttr(general.agent_name || "") +
          '" />',
      ) +
      field(
        "Idioma base",
        languageSelect(general.base_language || config.wpLanguage || "en"),
      ) +
      field(
        "Welcome message",
        '<textarea data-setting="general.welcome_message">' +
          escapeHtml(general.welcome_message || "") +
          "</textarea>",
      ) +
      field(
        "System prompt",
        '<textarea class="lg" data-setting="general.system_prompt">' +
          escapeHtml(general.system_prompt || "") +
          "</textarea>",
      ) +
      field(
        "Temperature",
        '<input type="number" step="0.1" min="0" max="1" data-setting="general.temperature" value="' +
          escapeAttr(String(general.temperature || 0.2)) +
          '" />',
      ) +
      field(
        "Max response tokens",
        '<input type="number" min="1" data-setting="general.max_response_tokens" value="' +
          escapeAttr(String(general.max_response_tokens || 500)) +
          '" />',
      ) +
      "</section>"
    );
  }

  function renderModels() {
    var provider = state.settings.provider || {};
    var fallback = state.settings.fallback_provider || {};
    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar"><h2>Models</h2><button type="button" data-action="save-settings">Guardar</button></div>' +
      '<div class="agentkit-grid-2">' +
      '<div class="agentkit-panel">' +
      "<h3>Provider primario</h3>" +
      field(
        "Provider",
        providerSelect("provider.name", provider.name || "openai"),
      ) +
      field(
        "API key",
        '<input data-setting="provider.api_key" value="' +
          escapeAttr(provider.api_key || "") +
          '" />',
      ) +
      field(
        "Chat model",
        modelControl(
          "provider.chat_model",
          provider.name || "openai",
          "chat",
          provider.chat_model || "",
        ),
      ) +
      field(
        "Embedding model",
        modelControl(
          "provider.embedding_model",
          provider.name || "openai",
          "embedding",
          provider.embedding_model || "",
        ),
      ) +
      '<button type="button" data-action="test-provider" data-target="primary">Test primario</button>' +
      "</div>" +
      '<div class="agentkit-panel">' +
      "<h3>Fallback provider</h3>" +
      field(
        "Enabled",
        '<select data-setting="fallback_provider.enabled"><option value="0"' +
          (!fallback.enabled ? " selected" : "") +
          '>No</option><option value="1"' +
          (fallback.enabled ? " selected" : "") +
          ">Si</option></select>",
      ) +
      field(
        "Provider",
        providerSelect("fallback_provider.name", fallback.name || "openai"),
      ) +
      field(
        "API key",
        '<input data-setting="fallback_provider.api_key" value="' +
          escapeAttr(fallback.api_key || "") +
          '" />',
      ) +
      field(
        "Chat model",
        modelControl(
          "fallback_provider.chat_model",
          fallback.name || "openai",
          "chat",
          fallback.chat_model || "",
        ),
      ) +
      field(
        "Embedding model",
        modelControl(
          "fallback_provider.embedding_model",
          fallback.name || "openai",
          "embedding",
          fallback.embedding_model || "",
        ),
      ) +
      '<button type="button" data-action="test-provider" data-target="fallback">Test fallback</button>' +
      "</div>" +
      "</div>" +
      '<div id="agentkit-provider-test"></div>' +
      "</section>"
    );
  }

  function renderSecurity() {
    var security = state.settings.security || {};
    var files = state.settings.files || {};
    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar"><h2>Security</h2><button type="button" data-action="save-settings">Guardar</button></div>' +
      field(
        "Rate limit por IP",
        '<input type="number" data-setting="security.rate_limit_ip" value="' +
          escapeAttr(String(security.rate_limit_ip || 30)) +
          '" />',
      ) +
      field(
        "Rate limit por sesión",
        '<input type="number" data-setting="security.rate_limit_session" value="' +
          escapeAttr(String(security.rate_limit_session || 50)) +
          '" />',
      ) +
      field(
        "Max message length",
        '<input type="number" data-setting="security.max_message_length" value="' +
          escapeAttr(String(security.max_message_length || 2000)) +
          '" />',
      ) +
      field(
        "Allowed domains (coma)",
        '<input data-setting="security.allowed_domains_csv" value="' +
          escapeAttr((security.allowed_domains || []).join(",")) +
          '" />',
      ) +
      field(
        "Max file size bytes",
        '<input type="number" data-setting="files.max_file_size" value="' +
          escapeAttr(String(files.max_file_size || 10485760)) +
          '" />',
      ) +
      field(
        "Max total files",
        '<input type="number" data-setting="files.max_total_files" value="' +
          escapeAttr(String(files.max_total_files || 50)) +
          '" />',
      ) +
      "</section>"
    );
  }

  function renderKnowledge() {
    var fileSettings = state.settings.files || {};
    var allowedTypes = (fileSettings.allowed_types || [])
      .map(function (type) {
        return String(type || "")
          .trim()
          .toLowerCase();
      })
      .filter(Boolean);
    var maxFileSize = Number(fileSettings.max_file_size || 0);
    var uploadCopy =
      "Tipos: " +
      (allowedTypes.length
        ? allowedTypes.join(", ")
        : "configuración por defecto") +
      " · Máx archivo: " +
      (maxFileSize > 0 ? formatBytes(maxFileSize) : "-");
    var hasUploadFeedback =
      state.upload.message && state.upload.status !== "idle";

    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar">' +
      "<h2>Knowledge Base</h2>" +
      '<div class="agentkit-actions-inline">' +
      '<button type="button" data-action="reindex-site">Reindexar sitio</button>' +
      '<button type="button" data-action="refresh-files">Actualizar archivos</button>' +
      "</div>" +
      "</div>" +
      '<div class="agentkit-upload-box">' +
      '<p class="agentkit-upload-help">Sube archivos aquí para agregarlos a la base de conocimiento.</p>' +
      '<div id="agentkit-dropzone" class="agentkit-dropzone" tabindex="0">' +
      "<strong>Arrastra archivos aquí</strong><span>o selecciónalos manualmente</span>" +
      '<input id="agentkit-file-input" type="file" multiple accept="' +
      escapeAttr(fileAccept(allowedTypes)) +
      '" />' +
      "</div>" +
      '<div class="agentkit-actions-inline">' +
      '<button type="button" data-action="upload-files">Subir archivos</button>' +
      "</div>" +
      '<p class="agentkit-upload-meta">' +
      escapeHtml(uploadCopy) +
      "</p>" +
      (hasUploadFeedback
        ? '<p class="agentkit-upload-status is-' +
          escapeAttr(state.upload.status) +
          '">' +
          escapeHtml(state.upload.message) +
          "</p>"
        : "") +
      "</div>" +
      '<h3 class="agentkit-subtitle">Registrar por Attachment ID (manual)</h3>' +
      '<div class="agentkit-inline-form agentkit-inline-form-manual">' +
      '<input id="agentkit-attachment-id" type="number" placeholder="Attachment ID" />' +
      '<button type="button" data-action="register-file">Registrar archivo</button>' +
      "</div>" +
      '<table class="agentkit-table"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Status</th><th>Chunks</th><th>Acciones</th></tr></thead><tbody>' +
      (state.files.length
        ? state.files
            .map(function (file) {
              return (
                "<tr>" +
                "<td>" +
                file.attachment_id +
                "</td>" +
                "<td>" +
                escapeHtml(file.original_name || "") +
                "</td>" +
                "<td>" +
                escapeHtml(file.file_type || "") +
                "</td>" +
                "<td>" +
                escapeHtml(file.status || "") +
                "</td>" +
                "<td>" +
                escapeHtml(String(file.chunk_count || 0)) +
                "</td>" +
                '<td><button type="button" data-action="reindex-file" data-id="' +
                file.attachment_id +
                '">Reindexar</button> <button type="button" data-action="delete-file" data-id="' +
                file.attachment_id +
                '">Eliminar</button></td>' +
                "</tr>"
              );
            })
            .join("")
        : '<tr><td colspan="6">No hay archivos registrados.</td></tr>') +
      "</tbody></table>" +
      "</section>"
    );
  }

  function renderConversations() {
    return (
      "" +
      "<section>" +
      '<div class="agentkit-toolbar">' +
      "<h2>Conversations</h2>" +
      '<div class="agentkit-actions-inline">' +
      '<button type="button" data-action="refresh-conversations">Actualizar</button>' +
      '<button type="button" data-action="export-conversations">Exportar JSON</button>' +
      "</div>" +
      "</div>" +
      '<table class="agentkit-table"><thead><tr><th>ID</th><th>Sesión</th><th>Página</th><th>Mensajes</th><th>Último mensaje</th><th>Acciones</th></tr></thead><tbody>' +
      (state.conversations.length
        ? state.conversations
            .map(function (conversation) {
              return (
                "<tr>" +
                "<td>" +
                conversation.id +
                "</td>" +
                "<td>" +
                escapeHtml(conversation.session_id || "") +
                "</td>" +
                "<td>" +
                escapeHtml(
                  conversation.page_title || conversation.page_url || "",
                ) +
                "</td>" +
                "<td>" +
                escapeHtml(String(conversation.message_count || 0)) +
                "</td>" +
                "<td>" +
                escapeHtml(conversation.last_message_at || "") +
                "</td>" +
                '<td><button type="button" data-action="view-conversation" data-id="' +
                conversation.id +
                '">Ver</button> <button type="button" data-action="delete-conversation" data-id="' +
                conversation.id +
                '">Eliminar</button></td>' +
                "</tr>"
              );
            })
            .join("")
        : '<tr><td colspan="6">No hay conversaciones todavía.</td></tr>') +
      "</tbody></table>" +
      '<div id="agentkit-conversation-viewer"></div>' +
      "</section>"
    );
  }

  function providerSelect(setting, value) {
    var options = ["openai", "anthropic", "gemini", "openrouter"];
    return (
      '<select data-setting="' +
      setting +
      '">' +
      options
        .map(function (option) {
          return (
            '<option value="' +
            option +
            '"' +
            (option === value ? " selected" : "") +
            ">" +
            option +
            "</option>"
          );
        })
        .join("") +
      "</select>"
    );
  }

  function modelControl(setting, providerName, type, value) {
    var options = ((providerCatalog[providerName] || {})[type] || []).slice();
    var current = value || options[0] || "";
    var isCustom = current && options.indexOf(current) === -1;
    var selectedValue = isCustom ? "__custom__" : current;

    return (
      '<div class="agentkit-model-control">' +
      '<select data-model-select="' +
      setting +
      '" data-provider-name="' +
      providerName +
      '" data-model-type="' +
      type +
      '">' +
      options
        .map(function (option) {
          var label = option === "none" ? "Sin embeddings nativos" : option;
          return (
            '<option value="' +
            option +
            '"' +
            (option === selectedValue ? " selected" : "") +
            ">" +
            escapeHtml(label) +
            "</option>"
          );
        })
        .join("") +
      '<option value="__custom__"' +
      (selectedValue === "__custom__" ? " selected" : "") +
      ">Custom…</option>" +
      "</select>" +
      '<input class="agentkit-model-custom' +
      (selectedValue === "__custom__" ? "" : " is-hidden") +
      '" data-setting="' +
      setting +
      '" value="' +
      escapeAttr(isCustom ? current : selectedValue === "none" ? "" : current) +
      '" placeholder="Model ID personalizado" />' +
      "</div>"
    );
  }

  function languageSelect(value) {
    var options = [
      [
        config.wpLanguage || "en",
        "WordPress (" + String(config.wpLanguage || "en").toUpperCase() + ")",
      ],
      ["auto", "Auto"],
      ["es", "Español"],
      ["en", "English"],
      ["pt", "Português"],
      ["fr", "Français"],
      ["de", "Deutsch"],
      ["it", "Italiano"],
      ["nl", "Nederlands"],
    ];

    var seen = {};
    options = options.filter(function (option) {
      if (seen[option[0]]) return false;
      seen[option[0]] = true;
      return true;
    });

    return (
      '<select data-setting="general.base_language">' +
      options
        .map(function (option) {
          return (
            '<option value="' +
            option[0] +
            '"' +
            (option[0] === value ? " selected" : "") +
            ">" +
            escapeHtml(option[1]) +
            "</option>"
          );
        })
        .join("") +
      "</select>"
    );
  }

  function card(label, value) {
    return (
      '<div class="agentkit-card"><span>' +
      escapeHtml(label) +
      "</span><strong>" +
      escapeHtml(String(value)) +
      "</strong></div>"
    );
  }

  function renderLineChart(title, points, valueKey, color) {
    if (!points.length) {
      return emptyPanel(title, "Aún no hay datos suficientes.");
    }

    var values = points.map(function (point) {
      return Number(point[valueKey] || 0);
    });
    var max = Math.max.apply(Math, values.concat([1]));
    var width = 520;
    var height = 220;
    var step = points.length > 1 ? width / (points.length - 1) : width;
    var path = values
      .map(function (value, index) {
        var x = index * step;
        var y = height - (value / max) * (height - 24) - 12;
        return (index === 0 ? "M" : "L") + x.toFixed(2) + " " + y.toFixed(2);
      })
      .join(" ");
    var area = path + " L " + width + " " + height + " L 0 " + height + " Z";
    var labels = points
      .map(function (point, index) {
        if (
          index !== 0 &&
          index !== points.length - 1 &&
          index % Math.ceil(points.length / 6) !== 0
        ) {
          return "";
        }
        var x = index * step;
        return (
          '<text x="' +
          x.toFixed(2) +
          '" y="214" text-anchor="middle">' +
          escapeHtml(shortDate(point.stat_date || "")) +
          "</text>"
        );
      })
      .join("");

    return (
      '<div class="agentkit-panel agentkit-chart-panel">' +
      '<div class="agentkit-panel-head"><h3>' +
      escapeHtml(title) +
      "</h3><span>Máx " +
      escapeHtml(String(max)) +
      "</span></div>" +
      '<svg class="agentkit-chart" viewBox="0 0 520 220" preserveAspectRatio="none">' +
      '<path d="' +
      area +
      '" fill="' +
      color +
      '22" stroke="none"></path>' +
      '<path d="' +
      path +
      '" fill="none" stroke="' +
      color +
      '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>' +
      labels +
      "</svg>" +
      "</div>"
    );
  }

  function renderBarChart(title, rows, labelKey, valueKey) {
    if (!rows.length) {
      return emptyPanel(title, "Aún no hay datos suficientes.");
    }
    var max = Math.max.apply(
      Math,
      rows
        .map(function (row) {
          return Number(row[valueKey] || 0);
        })
        .concat([1]),
    );

    return (
      '<div class="agentkit-panel agentkit-chart-panel">' +
      '<div class="agentkit-panel-head"><h3>' +
      escapeHtml(title) +
      "</h3></div>" +
      '<div class="agentkit-bars">' +
      rows
        .map(function (row) {
          var value = Number(row[valueKey] || 0);
          var width = Math.max(6, (value / max) * 100);
          return (
            "" +
            '<div class="agentkit-bar-row">' +
            '<div class="agentkit-bar-meta"><span>' +
            escapeHtml(row[labelKey] || "-") +
            "</span><strong>" +
            escapeHtml(String(value)) +
            "</strong></div>" +
            '<div class="agentkit-bar-track"><span class="agentkit-bar-fill" style="width:' +
            width.toFixed(2) +
            '%"></span></div>' +
            "</div>"
          );
        })
        .join("") +
      "</div>" +
      "</div>"
    );
  }

  function renderTopQuestions(rows) {
    if (!rows.length) {
      return emptyPanel("Top preguntas", "Aún no hay preguntas registradas.");
    }

    return (
      '<div class="agentkit-panel agentkit-chart-panel">' +
      '<div class="agentkit-panel-head"><h3>Top preguntas</h3></div>' +
      '<ol class="agentkit-top-list">' +
      rows
        .map(function (row) {
          return (
            "<li><span>" +
            escapeHtml(row.content || "") +
            "</span><strong>" +
            escapeHtml(String(row.total || 0)) +
            "</strong></li>"
          );
        })
        .join("") +
      "</ol>" +
      "</div>"
    );
  }

  function emptyPanel(title, message) {
    return (
      '<div class="agentkit-panel agentkit-chart-panel"><div class="agentkit-panel-head"><h3>' +
      escapeHtml(title) +
      '</h3></div><p class="agentkit-empty-copy">' +
      escapeHtml(message) +
      "</p></div>"
    );
  }

  function lastSeriesValue(series, key) {
    if (!series.length) return 0;
    return series[series.length - 1][key] || 0;
  }

  function shortDate(value) {
    if (!value) return "";
    var parts = String(value).split("-");
    return parts.length === 3 ? parts[2] + "/" + parts[1] : value;
  }

  function field(label, control) {
    return (
      '<label class="agentkit-field"><span>' +
      escapeHtml(label) +
      "</span>" +
      control +
      "</label>"
    );
  }

  function fileAccept(types) {
    if (!types || !types.length) return "";
    return types
      .map(function (type) {
        return "." + String(type).replace(/^\.+/, "");
      })
      .join(",");
  }

  function formatBytes(value) {
    var bytes = Number(value || 0);
    if (bytes <= 0) return "0 B";
    var units = ["B", "KB", "MB", "GB"];
    var idx = Math.min(
      Math.floor(Math.log(bytes) / Math.log(1024)),
      units.length - 1,
    );
    var sized = bytes / Math.pow(1024, idx);
    return sized.toFixed(idx === 0 ? 0 : 1) + " " + units[idx];
  }

  function setUploadStatus(status, message, processed, total, failures) {
    state.upload.status = status || "idle";
    state.upload.message = message || "";
    state.upload.processed = Number(processed || 0);
    state.upload.total = Number(total || 0);
    state.upload.failures = Number(failures || 0);
  }

  function bindEvents() {
    Array.prototype.forEach.call(
      root.querySelectorAll("[data-tab]"),
      function (button) {
        button.addEventListener("click", function () {
          state.activeTab = button.getAttribute("data-tab");
          render();
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll("[data-setting]"),
      function (input) {
        input.addEventListener("change", function () {
          setDeepValue(input.getAttribute("data-setting"), input.value);
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll("[data-model-select]"),
      function (select) {
        select.addEventListener("change", function () {
          var setting = select.getAttribute("data-model-select");
          var customInput = select.parentNode.querySelector(
            '[data-setting="' + setting + '"]',
          );
          if (select.value === "__custom__") {
            customInput.classList.remove("is-hidden");
            customInput.focus();
            return;
          }
          customInput.classList.add("is-hidden");
          customInput.value = select.value === "none" ? "" : select.value;
          setDeepValue(setting, customInput.value);
        });
      },
    );

    bindAction("save-settings", saveSettings);
    bindAction("refresh-dashboard", loadDashboard);
    bindAction("refresh-files", loadFiles);
    bindAction("refresh-conversations", loadConversations);
    bindAction("reindex-site", function () {
      api("/index", { method: "POST" }).then(loadDashboard);
    });
    bindAction("register-file", registerFile);
    bindAction("upload-files", uploadFiles);
    bindAction("export-conversations", exportConversations);

    var fileInput = root.querySelector("#agentkit-file-input");
    if (fileInput) {
      fileInput.addEventListener("change", function () {
        state.selectedFiles = Array.prototype.slice.call(fileInput.files || []);
        setUploadStatus(
          "idle",
          state.selectedFiles.length
            ? state.selectedFiles.length + " archivo(s) listos para subir."
            : "",
          0,
          0,
          0,
        );
        render();
      });
    }

    var dropzone = root.querySelector("#agentkit-dropzone");
    if (dropzone && fileInput) {
      dropzone.addEventListener("click", function () {
        fileInput.click();
      });
      dropzone.addEventListener("keydown", function (event) {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          fileInput.click();
        }
      });
      dropzone.addEventListener("dragover", function (event) {
        event.preventDefault();
        dropzone.classList.add("is-dragging");
      });
      dropzone.addEventListener("dragleave", function () {
        dropzone.classList.remove("is-dragging");
      });
      dropzone.addEventListener("drop", function (event) {
        event.preventDefault();
        dropzone.classList.remove("is-dragging");
        if (!event.dataTransfer || !event.dataTransfer.files) return;
        state.selectedFiles = Array.prototype.slice.call(
          event.dataTransfer.files || [],
        );
        setUploadStatus(
          "idle",
          state.selectedFiles.length + " archivo(s) listos para subir.",
          0,
          0,
          0,
        );
        render();
      });
    }

    Array.prototype.forEach.call(
      root.querySelectorAll('[data-action="test-provider"]'),
      function (button) {
        button.addEventListener("click", function () {
          api("/providers/test", {
            method: "POST",
            body: JSON.stringify({
              target: button.getAttribute("data-target"),
            }),
          }).then(function (result) {
            var box = root.querySelector("#agentkit-provider-test");
            box.innerHTML =
              '<pre class="agentkit-admin-json">' +
              escapeHtml(JSON.stringify(result, null, 2)) +
              "</pre>";
          });
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll('[data-action="reindex-file"]'),
      function (button) {
        button.addEventListener("click", function () {
          api("/files/reindex", {
            method: "POST",
            body: JSON.stringify({
              attachment_id: Number(button.getAttribute("data-id")),
            }),
          })
            .then(function (result) {
              if (result && result.code) {
                throw new Error(
                  result.message || "No se pudo reindexar el archivo.",
                );
              }
              setUploadStatus("success", "Reindexación iniciada.", 0, 0, 0);
              return loadFiles();
            })
            .catch(function (error) {
              setUploadStatus(
                "error",
                (error && error.message) || "No se pudo reindexar el archivo.",
                0,
                0,
                0,
              );
              render();
            });
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll('[data-action="delete-file"]'),
      function (button) {
        button.addEventListener("click", function () {
          api("/files", {
            method: "DELETE",
            body: JSON.stringify({
              attachment_id: Number(button.getAttribute("data-id")),
            }),
          })
            .then(function (result) {
              if (result && result.code) {
                throw new Error(
                  result.message || "No se pudo eliminar el archivo.",
                );
              }
              setUploadStatus(
                "success",
                "Archivo eliminado del índice.",
                0,
                0,
                0,
              );
              return loadFiles();
            })
            .catch(function (error) {
              setUploadStatus(
                "error",
                (error && error.message) || "No se pudo eliminar el archivo.",
                0,
                0,
                0,
              );
              render();
            });
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll('[data-action="delete-conversation"]'),
      function (button) {
        button.addEventListener("click", function () {
          api("/conversations", {
            method: "DELETE",
            body: JSON.stringify({
              id: Number(button.getAttribute("data-id")),
            }),
          }).then(loadConversations);
        });
      },
    );

    Array.prototype.forEach.call(
      root.querySelectorAll('[data-action="view-conversation"]'),
      function (button) {
        button.addEventListener("click", function () {
          api(
            "/conversations/messages?id=" +
              Number(button.getAttribute("data-id")),
          ).then(function (messages) {
            var viewer = root.querySelector("#agentkit-conversation-viewer");
            viewer.innerHTML =
              '<pre class="agentkit-admin-json">' +
              escapeHtml(JSON.stringify(messages, null, 2)) +
              "</pre>";
          });
        });
      },
    );
  }

  function bindAction(action, handler) {
    var node = root.querySelector('[data-action="' + action + '"]');
    if (node) node.addEventListener("click", handler);
  }

  function setDeepValue(path, value) {
    if (path === "security.allowed_domains_csv") {
      state.settings.security = state.settings.security || {};
      state.settings.security.allowed_domains = String(value)
        .split(",")
        .map(function (item) {
          return item.trim();
        })
        .filter(Boolean);
      return;
    }

    var parts = path.split(".");
    var cursor = state.settings;
    while (parts.length > 1) {
      var key = parts.shift();
      cursor[key] = cursor[key] || {};
      cursor = cursor[key];
    }
    var last = parts.shift();
    if (value === "0") value = false;
    if (value === "1") value = true;
    if (
      /^-?\d+(\.\d+)?$/.test(String(value)) &&
      path.indexOf("api_key") === -1 &&
      path.indexOf("name") === -1 &&
      path.indexOf("model") === -1 &&
      path.indexOf("language") === -1
    ) {
      value = Number(value);
    }
    cursor[last] = value;
  }

  function saveSettings() {
    api("/settings", {
      method: "POST",
      body: JSON.stringify(state.settings),
    }).then(function (settings) {
      state.settings = JSON.parse(JSON.stringify(settings || {}));
      render();
    });
  }

  function registerFile() {
    var input = root.querySelector("#agentkit-attachment-id");
    var id = Number(input && input.value);
    if (!id) {
      setUploadStatus(
        "error",
        "Debes indicar un Attachment ID válido.",
        0,
        0,
        0,
      );
      render();
      return;
    }

    api("/files", {
      method: "POST",
      body: JSON.stringify({ attachment_id: id }),
    })
      .then(function (result) {
        if (result && result.code) {
          throw new Error(result.message || "No se pudo registrar el archivo.");
        }
        setUploadStatus(
          "success",
          "Archivo registrado para indexación.",
          1,
          1,
          0,
        );
        return loadFiles();
      })
      .catch(function (error) {
        setUploadStatus(
          "error",
          (error && error.message) || "No se pudo registrar el archivo.",
          0,
          1,
          1,
        );
        render();
      });
  }

  function uploadFiles() {
    var input = root.querySelector("#agentkit-file-input");
    var files = state.selectedFiles.length
      ? state.selectedFiles.slice()
      : Array.prototype.slice.call((input && input.files) || []);

    if (!files.length) {
      setUploadStatus(
        "error",
        "Selecciona al menos un archivo para subir.",
        0,
        0,
        0,
      );
      render();
      return;
    }

    var processed = 0;
    var failures = 0;

    setUploadStatus(
      "uploading",
      "Iniciando subida de " + files.length + " archivo(s)...",
      0,
      files.length,
      0,
    );
    render();

    var sequence = Promise.resolve();

    files.forEach(function (file) {
      sequence = sequence.then(function () {
        setUploadStatus(
          "uploading",
          "Subiendo " +
            file.name +
            " (" +
            (processed + 1) +
            "/" +
            files.length +
            ")",
          processed,
          files.length,
          failures,
        );
        render();

        var formData = new FormData();
        formData.append("file", file, file.name);

        return apiForm("/files/upload", formData)
          .then(function () {
            processed += 1;
          })
          .catch(function (error) {
            processed += 1;
            failures += 1;
            setUploadStatus(
              "error",
              "Error en " +
                file.name +
                ": " +
                ((error && error.message) || "No se pudo subir el archivo."),
              processed,
              files.length,
              failures,
            );
            render();
          });
      });
    });

    sequence.then(function () {
      state.selectedFiles = [];
      if (input) {
        input.value = "";
      }

      if (failures > 0) {
        setUploadStatus(
          "error",
          "Subida finalizada con errores. Correctos: " +
            (files.length - failures) +
            "/" +
            files.length,
          processed,
          files.length,
          failures,
        );
      } else {
        setUploadStatus(
          "success",
          "Subida completada. Todos los archivos quedaron en cola de indexación.",
          processed,
          files.length,
          0,
        );
      }

      loadFiles();
      render();
    });
  }

  function exportConversations() {
    var blob = new Blob([JSON.stringify(state.conversations, null, 2)], {
      type: "application/json",
    });
    var url = URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = "agentkit-conversations.json";
    link.click();
    URL.revokeObjectURL(url);
  }

  function loadDashboard() {
    return api("/stats").then(function (stats) {
      state.stats = stats;
      if (state.activeTab === "dashboard") render();
    });
  }

  function loadFiles() {
    return api("/files").then(function (files) {
      state.files = files || [];
      syncFilesPolling();
      if (state.activeTab === "knowledge") render();
    });
  }

  function syncFilesPolling() {
    var needsPolling = (state.files || []).some(function (file) {
      var status = String((file && file.status) || "").toLowerCase();
      return status === "pending" || status === "indexing";
    });

    if (needsPolling && !state.filesPollingTimer) {
      state.filesPollingTimer = window.setInterval(function () {
        loadFiles();
      }, 5000);
      return;
    }

    if (!needsPolling && state.filesPollingTimer) {
      window.clearInterval(state.filesPollingTimer);
      state.filesPollingTimer = null;
    }
  }

  function loadConversations() {
    return api("/conversations").then(function (conversations) {
      state.conversations = conversations || [];
      if (state.activeTab === "conversations") render();
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/\n/g, " ");
  }

  Promise.all([loadDashboard(), loadFiles(), loadConversations()]).then(render);
})();
