import Dotenv from 'dotenv-webpack';
import {merge} from 'webpack-merge';
import common from './webpack.common.js';

export default merge(common, {
  watch: true,
  mode: 'development',
  devtool: 'inline-source-map',
  devServer: {
    static: './dist',
  },
  plugins: [
    new Dotenv({path: './.env.development'})
  ],
});