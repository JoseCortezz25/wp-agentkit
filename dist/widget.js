(function () {
  var config = window.agentkitWidget;
  if (!config) {
    return;
  }

  var texts = getTexts(config.baseLanguage || "en");

  function createWidget(container) {
    var root = document.createElement("div");
    root.className = "agentkit-widget";
    root.innerHTML =
      "" +
      '<div class="agentkit-header">' +
      escapeHtml(config.agentName || "AgentKit") +
      "</div>" +
      '<div class="agentkit-messages">' +
      '<div class="agentkit-message agentkit-message-ai">' +
      escapeHtml(config.welcomeMessage || "") +
      "</div>" +
      "</div>" +
      '<form class="agentkit-form">' +
      '<textarea class="agentkit-input" rows="3" placeholder="' +
      escapeHtml(texts.inputPlaceholder) +
      '"></textarea>' +
      '<button class="agentkit-submit" type="submit">' +
      escapeHtml(texts.send) +
      "</button>" +
      "</form>";

    var form = root.querySelector(".agentkit-form");
    var input = root.querySelector(".agentkit-input");
    var messages = root.querySelector(".agentkit-messages");
    var sessionId =
      self.crypto && self.crypto.randomUUID
        ? self.crypto.randomUUID()
        : "agentkit-" + Date.now();

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var value = (input.value || "").trim();
      if (!value) {
        return;
      }

      appendMessage(messages, "user", value);
      input.value = "";

      var aiNode = appendMessage(messages, "ai", "");

      streamRequest(value, sessionId, aiNode).catch(function () {
        aiNode.textContent = texts.connectionError;
      });
    });

    async function streamRequest(value, sessionId, aiNode) {
      var response = await fetch(
        config.streamUrl || config.restUrl + "/chat-stream",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": config.nonce,
          },
          body: JSON.stringify({
            message: value,
            session_id: sessionId,
            page_url: window.location.href,
            page_title: document.title,
          }),
        },
      );

      if (!response.body || !response.ok) {
        var fallbackResponse = await fetch(config.restUrl + "/chat", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": config.nonce,
          },
          body: JSON.stringify({
            message: value,
            session_id: sessionId,
            page_url: window.location.href,
            page_title: document.title,
          }),
        });
        var fallbackData = await fallbackResponse.json();
        aiNode.textContent =
          fallbackData && fallbackData.message
            ? fallbackData.message
            : texts.noResponse;
        return;
      }

      var reader = response.body.getReader();
      var decoder = new TextDecoder();
      var buffer = "";
      var currentEvent = "message";
      var dataBuffer = [];

      while (true) {
        var result = await reader.read();
        if (result.done) {
          break;
        }

        buffer += decoder.decode(result.value, { stream: true });
        var parts = buffer.split("\n");
        buffer = parts.pop() || "";

        parts.forEach(function (line) {
          if (line.indexOf("event:") === 0) {
            currentEvent = line.slice(6).trim();
            return;
          }

          if (line.indexOf("data:") === 0) {
            dataBuffer.push(line.slice(5).trim());
            return;
          }

          if (line.trim() === "") {
            flushEvent();
          }
        });
      }

      flushEvent();

      function flushEvent() {
        if (!dataBuffer.length) {
          return;
        }

        var raw = dataBuffer.join("\n");
        dataBuffer = [];

        try {
          var payload = JSON.parse(raw);
          if (currentEvent === "chunk" && payload.text) {
            aiNode.textContent += payload.text;
            messages.scrollTop = messages.scrollHeight;
          }
          if (currentEvent === "done" && payload.message) {
            aiNode.textContent = payload.message;
            messages.scrollTop = messages.scrollHeight;
          }
          if (currentEvent === "error" && payload.message) {
            aiNode.textContent = payload.message;
          }
        } catch (error) {
          aiNode.textContent = texts.streamError;
        }

        currentEvent = "message";
      }
    }

    if (container) {
      container.appendChild(root);
    } else {
      document.body.appendChild(root);
    }
  }

  function appendMessage(messages, role, text) {
    var node = document.createElement("div");
    node.className =
      "agentkit-message " +
      (role === "user" ? "agentkit-message-user" : "agentkit-message-ai");
    node.textContent = text;
    messages.appendChild(node);
    messages.scrollTop = messages.scrollHeight;
    return node;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getTexts(language) {
    var map = {
      es: {
        inputPlaceholder: "Escribe tu pregunta",
        send: "Enviar",
        connectionError: "Hubo un error conectando con AgentKit.",
        noResponse: "No fue posible obtener una respuesta.",
        streamError: "No fue posible procesar el stream.",
      },
      pt: {
        inputPlaceholder: "Escreva sua pergunta",
        send: "Enviar",
        connectionError: "Ocorreu um erro ao conectar com o AgentKit.",
        noResponse: "Não foi possível obter uma resposta.",
        streamError: "Não foi possível processar o streaming.",
      },
      fr: {
        inputPlaceholder: "Écrivez votre question",
        send: "Envoyer",
        connectionError:
          "Une erreur est survenue lors de la connexion à AgentKit.",
        noResponse: "Impossible d’obtenir une réponse.",
        streamError: "Impossible de traiter le flux.",
      },
      de: {
        inputPlaceholder: "Schreibe deine Frage",
        send: "Senden",
        connectionError: "Fehler beim Verbinden mit AgentKit.",
        noResponse: "Es konnte keine Antwort abgerufen werden.",
        streamError: "Der Stream konnte nicht verarbeitet werden.",
      },
      it: {
        inputPlaceholder: "Scrivi la tua domanda",
        send: "Invia",
        connectionError:
          "Si è verificato un errore durante la connessione ad AgentKit.",
        noResponse: "Non è stato possibile ottenere una risposta.",
        streamError: "Non è stato possibile elaborare lo streaming.",
      },
      nl: {
        inputPlaceholder: "Typ je vraag",
        send: "Verzenden",
        connectionError:
          "Er is een fout opgetreden bij het verbinden met AgentKit.",
        noResponse: "Er kon geen antwoord worden opgehaald.",
        streamError: "De stream kon niet worden verwerkt.",
      },
      en: {
        inputPlaceholder: "Type your question",
        send: "Send",
        connectionError: "There was an error connecting to AgentKit.",
        noResponse: "It was not possible to get a response.",
        streamError: "It was not possible to process the stream.",
      },
    };

    if (language === "auto") {
      language = (navigator.language || "en").slice(0, 2).toLowerCase();
    }

    return map[language] || map.en;
  }

  var embeds = document.querySelectorAll('[data-agentkit-embed="1"]');
  if (embeds.length) {
    Array.prototype.forEach.call(embeds, function (node) {
      createWidget(node);
    });
    return;
  }

  createWidget(null);
})();
