import { createRoot } from 'react-dom/client';
import App from './App';
import { HashRouter } from 'react-router-dom';

createRoot(document.getElementById('display-user')).render(
      <HashRouter basename='/test'>
            <App />
      </HashRouter>
);
