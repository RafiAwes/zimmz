import express from "express";
import { createServer } from "http";
import { Server } from "socket.io";

const app = express();
const server = createServer(app);
app.use(express.json());

const baseUrl = "http://10.10.10.45:8001/api";

const io = new Server(server, {
    cors: {
        origin: "*",
    },
});

const users = {};

// const groups = {};

io.on("connection", (socket) => {
    console.log(`User connected: ${socket.id}`);

    socket.on('login', ({userId}) =>{
        console.log(`User ${userId} logged in`);
        updateUserStatus(userId, true);
    })

    //Handle private messages
    socket.on("joinRoom", ({ userId, receiverId }) => {
        users[userId] = socket.id;
        const roomId = [userId, receiverId].sort().join("-");
        socket.join(roomId);
        console.log(`User ${userId} joined room ${roomId}`);
        console.log("roomId:", roomId);
        //update user status
        // updateUserStatus(userId, true);

        socket.on(
            "send_message",
            ({
                userId,
                receiverId,
                conversation_id,
                message,
            }) => {
                const roomId = [userId, receiverId].sort().join("-");
                io.to(roomId).emit("receive_message", {
                    conversation_id,
                    message,
                });
                console.log(`Message in room ${roomId}: ${message}`);
            }
        );

        //Handle group messages
        socket.on("joinGroup", ({ userId, groupId }) => {
            socket.join(groupId);
            console.log(`User ${userId} joined group ${groupId}`);
        });
        socket.on("sendGroupMessage", ({ groupId, userId, message }) => {
            io.to(groupId).emit("receiveGroupMessage", {
                senderId: userId,
                message,
                timeStamp: new Date().toLocaleString("en-GB", {
                    day: "2-digit",
                    month: "short",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true,
                }),
            });
            console.log(
                `Message in group ${groupId} from ${userId}: ${message}`
            );
        });

        //handle user disconnection
        socket.on("disconnect", () => {
            console.log(`User disconnected: ${socket.id}`);
            const userId = getKeyByValue(users, socket.id);
            delete users[userId];
            console.log(`User ${userId} disconnected`);

            updateUserStatus(userId, false);
        });

        // Get key from value in object
        function getKeyByValue(object, value) {
            return Object.keys(object).find((key) => object[key] === value);
        }
    });
});

// receive broadcasts from Laravel and emit to socket.io rooms
app.post('/broadcast', (req, res) => {
    try {
        const { channels, event, payload } = req.body || {};
        if (!channels || !event) {
            return res.status(400).json({ error: 'channels and event are required' });
        }

        const chans = Array.isArray(channels) ? channels : [channels];
        chans.forEach((ch) => {
            io.to(ch).emit(event, payload || {});
            console.log(`Emitted event ${event} to channel ${ch}`);
        });

        return res.sendStatus(200);
    } catch (err) {
        console.error('Broadcast error:', err);
        return res.sendStatus(500);
    }
});
server.listen(3001, "10.10.10.45", () => {
    console.log("Server running on http://10.10.10.45:3001");
});

//user status update function
function updateUserStatus(userId, isActive) {
    try {
        fetch(`${baseUrl}/update-status`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                userId,
                is_active: isActive,
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                console.log("Success:", data);
            })
            .catch((error) => {
                console.error("Error:", error);
            });
    } catch (error) {
        console.log("Error:", error);
    }
}
 