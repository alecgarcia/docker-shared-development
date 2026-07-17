import 'dotenv/config';
import Anthropic from '@anthropic-ai/sdk';
import { WebSocketServer } from 'ws';

const PORT = process.env.PORT ?? 3003;
const client = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });

/** @type {Map<string, Array<{role: string, content: string}>>} */
const sessions = new Map();

const wss = new WebSocketServer({ port: Number(PORT) });

wss.on('connection', (ws) => {
    console.log('ConversationRelay session connected');

    let callSid = null;
    let systemPrompt = 'You are a helpful voice assistant. Keep responses concise and conversational — they will be spoken aloud via text-to-speech.';

    ws.on('message', async (data) => {
        const msg = JSON.parse(data);
        console.log('[rx]', msg.type, JSON.stringify(msg));

        if (msg.type === 'setup') {
            callSid = msg.callSid;
            sessions.set(callSid, []);

            const params = msg.customParameters ?? {};
            if (params.systemPrompt) {
                systemPrompt = params.systemPrompt;
            }

            const now = new Date().toLocaleString('en-US', {
                timeZone: 'America/Indiana/Indianapolis',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
            systemPrompt += `\n\nCurrent date and time (Fort Wayne, IN): ${now}`;

            if (params.multilingual === 'true') {
                systemPrompt += '\n\nDetect the language the caller is speaking and always respond in that same language. If they speak Spanish, respond in Spanish. If they speak English, respond in English. Match their language naturally throughout the entire conversation.';
            }

            if (params.callerContext) {
                try {
                    const ctx = JSON.parse(params.callerContext);
                    const lines = [];
                    if (ctx.name) lines.push(`- Caller name on file: ${ctx.name} — confirm their identity before proceeding (people share numbers), then use their name naturally`);
                    if (ctx.isReturningCustomer) lines.push(`- Returning Sweetwater customer (Customer ID: ${ctx.customerId})`);
                    if (ctx.assignedSalesEngineer) lines.push(`- Their assigned Sales Engineer: ${ctx.assignedSalesEngineer}`);
                    if (lines.length > 0) {
                        systemPrompt += `\n\nCALLER CONTEXT (pre-loaded from our system — use this, do not ask for info you already have):\n${lines.join('\n')}`;
                    }
                    console.log(`[session] caller context loaded: ${JSON.stringify(ctx)}`);
                } catch (e) {
                    console.error('[session] Failed to parse callerContext:', e.message);
                }
            }

            console.log(`[session] ${callSid} initiated — agentType: ${params.agentType ?? 'default'}`);
            return;
        }

        if (msg.type === 'prompt') {
            const history = sessions.get(callSid) ?? [];
            history.push({ role: 'user', content: msg.voicePrompt });

            let fullResponse = '';

            try {
                const stream = await client.messages.stream({
                    model: 'claude-haiku-4-5-20251001',
                    max_tokens: 1024,
                    system: systemPrompt,
                    messages: history,
                });

                for await (const event of stream) {
                    if (
                        event.type === 'content_block_delta' &&
                        event.delta?.type === 'text_delta'
                    ) {
                        const token = event.delta.text.replace(/\*/g, '');
                        fullResponse += token;
                        const reply = { type: 'text', token, last: false };
                        console.log('[tx]', reply);
                        ws.send(JSON.stringify(reply));
                    }
                }

                ws.send(JSON.stringify({ type: 'text', token: '', last: true }));
                history.push({ role: 'assistant', content: fullResponse });
                sessions.set(callSid, history);
            } catch (err) {
                console.error('[claude] error:', err.message);
                ws.send(JSON.stringify({ type: 'text', token: 'Sorry, something went wrong.', last: true }));
            }

            return;
        }

        if (msg.type === 'session.end' || msg.type === 'end') {
            console.log(`[session] ${callSid} ended`);
            sessions.delete(callSid);
            return;
        }
    });

    ws.on('close', () => {
        if (callSid) {
            sessions.delete(callSid);
        }
        console.log('ConversationRelay session disconnected');
    });

    ws.on('error', (err) => console.error('WebSocket error:', err));
});

console.log(`ConversationRelay WebSocket server listening on port ${PORT}`);
