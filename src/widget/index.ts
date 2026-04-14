import "./widget.css";

type WidgetConfig = {
  restUrl: string;
  nonce: string;
  welcomeMessage: string;
  agentName: string;
};

declare global {
  interface Window {
    agentkitWidget?: WidgetConfig;
  }
}

const config = window.agentkitWidget;

if (config) {
  const mountTargets = document.querySelectorAll<HTMLElement>(
    '[data-agentkit-embed="1"]',
  );

  const createWidget = (container?: HTMLElement) => {
    const root = document.createElement("div");
    root.className = "agentkit-widget";
    root.innerHTML = `
      <div class="agentkit-header">${config.agentName}</div>
      <div class="agentkit-messages"><div class="agentkit-message agentkit-message-ai">${config.welcomeMessage}</div></div>
      <form class="agentkit-form">
        <textarea class="agentkit-input" rows="3" placeholder="Escribe tu pregunta"></textarea>
        <button class="agentkit-submit" type="submit">Enviar</button>
      </form>
    `;

    const form = root.querySelector<HTMLFormElement>(".agentkit-form");
    const input = root.querySelector<HTMLTextAreaElement>(".agentkit-input");
    const messages = root.querySelector<HTMLElement>(".agentkit-messages");
    const sessionId = crypto.randomUUID();

    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const value = input?.value.trim() ?? "";
      if (!value || !messages || !input) return;

      messages.insertAdjacentHTML(
        "beforeend",
        `<div class="agentkit-message agentkit-message-user"></div>`,
      );
      const userNode = messages.lastElementChild as HTMLElement;
      userNode.textContent = value;
      input.value = "";

      const response = await fetch(`${config.restUrl}/chat`, {
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

      const data = (await response.json()) as { message?: string };
      messages.insertAdjacentHTML(
        "beforeend",
        `<div class="agentkit-message agentkit-message-ai"></div>`,
      );
      const aiNode = messages.lastElementChild as HTMLElement;
      aiNode.textContent =
        data.message ?? "No fue posible obtener una respuesta.";
      messages.scrollTop = messages.scrollHeight;
    });

    if (container) {
      container.appendChild(root);
    } else {
      document.body.appendChild(root);
    }
  };

  if (mountTargets.length) {
    mountTargets.forEach((target) => createWidget(target));
  } else {
    createWidget();
  }
}
