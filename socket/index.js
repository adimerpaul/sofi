const express = require('express');
const { createServer } = require('http');
const { join } = require('path');
const { Server } = require('socket.io');
const cors = require('cors');

require('dotenv').config();

const app = express();
const server = createServer(app);

const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    }
});

app.use(express.json({ limit: '1mb' }));

app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization']
}));

function emitEvent(eventName, payload = {}) {
    const event = String(eventName || '').trim();

    if (!event) {
        throw new Error('El nombre del evento es obligatorio');
    }

    io.emit(event, payload);

    return event;
}

app.get('/', (req, res) => {
    res.sendFile(join(__dirname, 'index.html'));
});

app.get('/send', (req, res) => {
    io.emit('reservas', 'Hola desde el servidor');
    res.send('Mensaje enviado');
});

app.get('/silSolicitud', (req, res) => {
    io.emit('silSolicitud', 'Hola desde el servidor');
    res.send('Mensaje enviado');
});

app.get('/votacion', (req, res) => {
    emitEvent('votacion', {
        title: 'Nuevo dato registrado',
        message: 'Evento manual de votación',
        kind: 'manual'
    });

    res.json({
        ok: true,
        event: 'votacion'
    });
});

/**
 * Laravel llama a esta ruta para emitir eventos.
 */
app.post('/notify', (req, res) => {
    try {
        const { event, data } = req.body || {};

        if (!event || typeof event !== 'string') {
            return res.status(422).json({
                success: false,
                message: 'El campo event es obligatorio'
            });
        }

        const emittedEvent = emitEvent(event, data ?? {});

        console.log(`[NOTIFY] Evento emitido: ${emittedEvent}`, data);

        return res.json({
            success: true,
            event: emittedEvent
        });
    } catch (error) {
        console.error('[NOTIFY] Error:', error);

        return res.status(500).json({
            success: false,
            message: 'No se pudo emitir el evento'
        });
    }
});

/**
 * Esta ruta hace lo mismo que /notify.
 * Puedes mantenerla si algún sistema ya la utiliza.
 */
app.post('/emit', (req, res) => {
    try {
        const event = emitEvent(
            req.body?.event,
            req.body?.payload ?? {}
        );

        return res.json({
            ok: true,
            event
        });
    } catch (error) {
        return res.status(422).json({
            ok: false,
            message: error.message
        });
    }
});

io.on('connection', (socket) => {
    console.log('Cliente conectado:', socket.id);

    socket.on('disconnect', (reason) => {
        console.log('Cliente desconectado:', socket.id, reason);
    });

    socket.on('reservas-Ayacucho', (msg) => {
        console.log('reservas-Ayacucho:', msg);
        io.emit('reservas-ayacucho', msg);
    });

    socket.on('reservas-Oquendo', (msg) => {
        console.log('reservas-Oquendo:', msg);
        io.emit('reservas-oquendo', msg);
    });

    socket.on('silSolicitud', (msg) => {
        console.log('silSolicitud:', msg);
        io.emit('silSolicitud', msg);
    });

    socket.on('votacion', (msg) => {
        console.log('votacion:', msg);
        io.emit('votacion', msg);
    });

    socket.broadcast.emit('hi');

    socket.on('reservas-sucre-aventura', (msg) => {
        console.log('reservas-sucre-aventura:', msg);
        io.emit('reservas-sucre-aventura', msg);
    });
});

const port = process.env.PORT || 3014;

server.listen(port, '127.0.0.1', () => {
    console.log(`Socket funcionando en http://127.0.0.1:${port}`);
});
