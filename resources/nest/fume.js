"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
  return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.handler = void 0;
const core_1 = require("@nestjs/core");
const platform_express_1 = require("@nestjs/platform-express");
const serverless_express_1 = __importDefault(require("@vendia/serverless-express"));
const express_1 = __importDefault(require("express"));
const app_module_1 = require("./app.module");
let cachedServer;
async function bootstrap() {
  if (!cachedServer) {
    const expressApp = express_1.default();
    const nestApp = await core_1.NestFactory.create(app_module_1.AppModule, new platform_express_1.ExpressAdapter(expressApp));
    nestApp.enableCors();
    await nestApp.init();
    cachedServer = serverless_express_1.default({ app: expressApp });
  }
  return cachedServer;
}
const handler = async (event, context, callback) => {
  const server = await bootstrap();
  return server(event, context, callback);
};
exports.handler = handler;
