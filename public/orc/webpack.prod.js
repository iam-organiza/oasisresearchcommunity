import Dotenv from 'dotenv-webpack';
import {merge} from 'webpack-merge';
import common from './webpack.common.js';

export default merge(common, {
  mode: 'production',
  plugins: [
    new Dotenv({path: './.env.production'})
  ],
});