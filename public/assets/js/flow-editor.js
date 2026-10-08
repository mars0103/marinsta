/* Define os tipos de nó do Marinsta em cima do LiteGraph.js e monta/lê o grafo
   visual (Gatilho -> Responder comentário -> Enviar DM). O grafo é só uma
   representação visual dos MESMOS campos do formulário normal — ao salvar,
   ele popula um form escondido e envia pro mesmo endpoint de sempre. */

const MATCH_TYPE = [["Qualquer uma das palavras", "any"], ["Todas as palavras", "all"]];
const TARGET_SCOPE = [["Todas as publicações", "all"], ["Só a próxima publicação", "next"], ["Publicação específica", "specific"]];
const SOURCE_TYPE = [["Texto fixo", "text"], ["Pack de respostas", "pack"]];

function labelFor(list, value) {
    const found = list.find((pair) => pair[1] === value);
    return found ? found[0] : list[0][0];
}
function valueFor(list, label) {
    const found = list.find((pair) => pair[0] === label);
    return found ? found[1] : list[0][1];
}

function packOptions(packs) {
    return ["Nenhum", ...packs.map((p) => p.name)];
}
function packIdFromLabel(packs, label) {
    const found = packs.find((p) => p.name === label);
    return found ? found.id : null;
}
function packLabelFromId(packs, id) {
    const found = packs.find((p) => String(p.id) === String(id));
    return found ? found.name : "Nenhum";
}

function mediaOptions(mediaList) {
    return ["Nenhuma", ...mediaList.map(mediaLabel)];
}
function mediaLabel(m) {
    const caption = (m.caption || "Sem legenda").slice(0, 40);
    const date = (m.timestamp || "").slice(0, 10);
    return `${caption} — ${date}`;
}
function mediaIdFromLabel(mediaList, label) {
    const found = mediaList.find((m) => mediaLabel(m) === label);
    return found ? found.id : null;
}
function mediaLabelFromId(mediaList, id) {
    const found = mediaList.find((m) => String(m.id) === String(id));
    return found ? mediaLabel(found) : "Nenhuma";
}

const NODE_WIDTH = 300;
const ACCENT = "#4F46E5";

function applyCleanTheme(canvas) {
    LiteGraph.NODE_TITLE_COLOR = "#ffffff";
    LiteGraph.NODE_SELECTED_TITLE_COLOR = "#ffffff";
    LiteGraph.NODE_TEXT_SIZE = 13;
    LiteGraph.NODE_SUBTEXT_SIZE = 11;
    LiteGraph.NODE_TITLE_HEIGHT = 32;
    LiteGraph.NODE_DEFAULT_BGCOLOR = "#ffffff";
    LiteGraph.NODE_DEFAULT_BOXCOLOR = ACCENT;
    LiteGraph.NODE_WIDGET_HEIGHT = 26;
    LiteGraph.WIDGET_BGCOLOR = "#f6f8fc";
    LiteGraph.WIDGET_TEXT_COLOR = "#111827";
    LiteGraph.WIDGET_SECONDARY_TEXT_COLOR = "#6b7280";
    LiteGraph.WIDGET_OUTLINE_COLOR = "#e3e8f0";
    LiteGraph.LINK_COLOR = "#818cf8";
    LiteGraph.EVENT_LINK_COLOR = ACCENT;
    LiteGraph.CONNECTING_LINK_COLOR = ACCENT;
    LiteGraph.DEFAULT_SHADOW_COLOR = "rgba(16,24,40,0.08)";

    const fontStack = "'Space Grotesk', system-ui, sans-serif";
    canvas.clear_background_color = "#f6f8fc";
    canvas.background_image = null;
    canvas.render_canvas_border = false;
    canvas.render_connections_shadows = false;
    canvas.render_curved_connections = true;
    canvas.render_connection_arrows = true;
    canvas.render_link_tooltip = false;
    canvas.links_render_mode = LiteGraph.SPLINE_LINK;
    canvas.title_text_font = `600 13px ${fontStack}`;
    canvas.inner_text_font = `normal 12px ${fontStack}`;

    // esconde os menus padrão da biblioteca (busca de nó, "adicionar nó" com
    // dezenas de tipos genéricos tipo math/string/audio) — aqui só existem os
    // 3 blocos do Marinsta, não faz sentido deixar criar outros ainda.
    canvas.allow_searchbox = false;
    canvas.getCanvasMenuOptions = function () { return null; };
    canvas.getNodeMenuOptions = function (node) {
        return [
            { content: "Remover", callback: () => { canvas.graph.remove(node); } },
        ];
    };
    canvas.showLinkMenu = function () { return false; };
}

const TEXTAREA_HEIGHT = 56;

function addTextareaWidget(node, label, onChange) {
    const widget = node.addWidget("text", label, "", onChange, { multiline: true });
    widget.computeSize = () => [0, TEXTAREA_HEIGHT];
    return widget;
}

function registerMarinstaNodes(packs, mediaList) {
    function TriggerNode() {
        this.addOutput("próximo", "flow");
        this.properties = { keywords: "", match_type: "any", target_scope: "all", target_media_id: "" };
        this.addWidget("text", "Palavras-chave", "", (v) => (this.properties.keywords = v));
        this.addWidget("combo", "Regra", labelFor(MATCH_TYPE, "any"), (v) => (this.properties.match_type = valueFor(MATCH_TYPE, v)), { values: MATCH_TYPE.map((p) => p[0]) });
        this.addWidget("combo", "Publicação", labelFor(TARGET_SCOPE, "all"), (v) => (this.properties.target_scope = valueFor(TARGET_SCOPE, v)), { values: TARGET_SCOPE.map((p) => p[0]) });
        this.addWidget("combo", "Qual", "Nenhuma", (v) => (this.properties.target_media_id = mediaIdFromLabel(mediaList, v)), { values: mediaOptions(mediaList) });
        this.size = [NODE_WIDTH, 150];
        this.color = ACCENT;
        this.boxcolor = "#ffffff";
    }
    TriggerNode.title = "Gatilho: Comentário no Feed";
    LiteGraph.registerNodeType("marinsta/trigger", TriggerNode);

    function ReplyCommentNode() {
        this.addInput("gatilho", "flow");
        this.addOutput("próximo", "flow");
        this.properties = { source: "text", text: "", pack_id: null };
        this.addWidget("combo", "Tipo", labelFor(SOURCE_TYPE, "text"), (v) => (this.properties.source = valueFor(SOURCE_TYPE, v)), { values: SOURCE_TYPE.map((p) => p[0]) });
        addTextareaWidget(this, "Texto", (v) => (this.properties.text = v));
        this.addWidget("combo", "Pack", "Nenhum", (v) => (this.properties.pack_id = packIdFromLabel(packs, v)), { values: packOptions(packs) });
        this.size = [NODE_WIDTH, 160];
        this.color = ACCENT;
        this.boxcolor = "#ffffff";
    }
    ReplyCommentNode.title = "Ação: Responder comentário";
    LiteGraph.registerNodeType("marinsta/reply_comment", ReplyCommentNode);

    function SendDmNode() {
        this.addInput("gatilho", "flow");
        this.properties = { source: "text", text: "", pack_id: null, button_label: "", button_url: "" };
        this.addWidget("combo", "Tipo", labelFor(SOURCE_TYPE, "text"), (v) => (this.properties.source = valueFor(SOURCE_TYPE, v)), { values: SOURCE_TYPE.map((p) => p[0]) });
        addTextareaWidget(this, "Texto (aceita link)", (v) => (this.properties.text = v));
        this.addWidget("combo", "Pack", "Nenhum", (v) => (this.properties.pack_id = packIdFromLabel(packs, v)), { values: packOptions(packs) });
        this.addWidget("text", "Texto do botão (opcional)", "", (v) => (this.properties.button_label = v));
        this.addWidget("text", "Link do botão", "", (v) => (this.properties.button_url = v));
        this.size = [NODE_WIDTH, 200];
        this.color = ACCENT;
        this.boxcolor = "#ffffff";
    }
    SendDmNode.title = "Ação: Enviar DM";
    LiteGraph.registerNodeType("marinsta/send_dm", SendDmNode);

    function DelayNode() {
        this.addInput("gatilho", "flow");
        this.addOutput("próximo", "flow");
        this.properties = { minutes: 0 };
        this.addWidget("number", "Minutos de espera", 0, (v) => (this.properties.minutes = Math.max(0, Math.round(v))), { min: 0, step: 1, precision: 0 });
        this.size = [NODE_WIDTH, 90];
        this.color = ACCENT;
        this.boxcolor = "#ffffff";
    }
    DelayNode.title = "Esperar antes de responder";
    LiteGraph.registerNodeType("marinsta/delay", DelayNode);
}

function buildGraph(canvasEl, automation, packs, mediaList) {
    registerMarinstaNodes(packs, mediaList);

    const graph = new LGraph();
    const canvas = new LGraphCanvas(canvasEl, graph);
    applyCleanTheme(canvas);

    const trigger = LiteGraph.createNode("marinsta/trigger");
    trigger.pos = [40, 120];
    trigger.properties.keywords = automation.keywords || "";
    trigger.properties.match_type = automation.match_type || "any";
    trigger.properties.target_scope = automation.target_scope || "all";
    trigger.properties.target_media_id = automation.target_media_id || null;
    trigger.widgets[0].value = trigger.properties.keywords;
    trigger.widgets[1].value = labelFor(MATCH_TYPE, trigger.properties.match_type);
    trigger.widgets[2].value = labelFor(TARGET_SCOPE, trigger.properties.target_scope);
    trigger.widgets[3].value = mediaLabelFromId(mediaList, trigger.properties.target_media_id);
    graph.add(trigger);

    let column = 1;
    let upstream = trigger;

    if (automation.delay_minutes > 0) {
        const delay = LiteGraph.createNode("marinsta/delay");
        delay.pos = [40 + (NODE_WIDTH + 80) * column, 120];
        delay.properties.minutes = automation.delay_minutes;
        delay.widgets[0].value = automation.delay_minutes;
        graph.add(delay);
        upstream.connect(0, delay, 0);
        upstream = delay;
        column++;
    }

    const reply = LiteGraph.createNode("marinsta/reply_comment");
    reply.pos = [40 + (NODE_WIDTH + 80) * column, 120];
    reply.properties.source = automation.reply_pack_id ? "pack" : "text";
    reply.properties.text = automation.reply_text || "";
    reply.properties.pack_id = automation.reply_pack_id || null;
    reply.widgets[0].value = labelFor(SOURCE_TYPE, reply.properties.source);
    reply.widgets[1].value = reply.properties.text;
    reply.widgets[2].value = packLabelFromId(packs, reply.properties.pack_id);
    graph.add(reply);
    upstream.connect(0, reply, 0);
    column++;

    if (automation.send_dm) {
        const dm = LiteGraph.createNode("marinsta/send_dm");
        dm.pos = [40 + (NODE_WIDTH + 80) * column, 120];
        dm.properties.source = automation.dm_pack_id ? "pack" : "text";
        dm.properties.text = automation.dm_text || "";
        dm.properties.pack_id = automation.dm_pack_id || null;
        dm.properties.button_label = automation.dm_button_label || "";
        dm.properties.button_url = automation.dm_button_url || "";
        dm.widgets[0].value = labelFor(SOURCE_TYPE, dm.properties.source);
        dm.widgets[1].value = dm.properties.text;
        dm.widgets[2].value = packLabelFromId(packs, dm.properties.pack_id);
        dm.widgets[3].value = dm.properties.button_label;
        dm.widgets[4].value = dm.properties.button_url;
        graph.add(dm);
        reply.connect(0, dm, 0);
    }

    canvas.draw(true, true);
    return { graph, canvas };
}

function findNodeByType(graph, type) {
    return graph._nodes.find((n) => n.type === type) || null;
}

function nextConnectedNode(graph, node) {
    if (!node.outputs || !node.outputs[0] || !node.outputs[0].links || !node.outputs[0].links.length) {
        return null;
    }
    const linkId = node.outputs[0].links[0];
    const link = graph.links[linkId];
    return link ? graph.getNodeById(link.target_id) : null;
}

function collectAutomationFromGraph(graph) {
    const trigger = findNodeByType(graph, "marinsta/trigger");
    if (!trigger) {
        return null;
    }
    let cursor = nextConnectedNode(graph, trigger);
    let delayMinutes = 0;
    if (cursor && cursor.type === "marinsta/delay") {
        delayMinutes = cursor.properties.minutes || 0;
        cursor = nextConnectedNode(graph, cursor);
    }
    const reply = cursor && cursor.type === "marinsta/reply_comment" ? cursor : null;
    const dm = reply ? nextConnectedNode(graph, reply) : null;

    return {
        keywords: trigger.properties.keywords || "",
        match_type: trigger.properties.match_type || "any",
        target_scope: trigger.properties.target_scope || "all",
        target_media_id: trigger.properties.target_media_id || "",
        delay_minutes: delayMinutes,
        reply_source: reply ? reply.properties.source : "text",
        reply_text: reply ? reply.properties.text : "",
        reply_pack_id: reply ? reply.properties.pack_id : "",
        send_dm: !!dm,
        dm_source: dm ? dm.properties.source : "text",
        dm_text: dm ? dm.properties.text : "",
        dm_pack_id: dm ? dm.properties.pack_id : "",
        dm_button_label: dm ? dm.properties.button_label : "",
        dm_button_url: dm ? dm.properties.button_url : "",
    };
}
