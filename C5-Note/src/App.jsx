import './App.css';
import { HashRouter  as Router,Routes,Route,Link } from "react-router-dom";
import { Home } from './Pages/home';
import { Page1 } from './Pages/page1';

import { Simple_notebook } from './Pages/simple_notebook';
import { NotebookDetail } from "./Pages/notebookDetails";


import { PageNotebooks } from './Pages/notebooks';
import { SignUpPage } from './Pages/signUpPage';
import { Profile } from './Pages/profile';
import { VerifyEmail } from './Pages/verify_email';
import { Forgot_login } from './Pages/forgot_login';
import { Reset_Password } from './Pages/reset_password';
// Test Pages
import { TestPageWrite } from './Pages/test/testpagewrite';
import { ToolTest } from './Pages/toolbar_testing'
import { Etest } from './Pages/Etest'


function App() {

  return (
    <Router>
      <Routes>
        <Route path="/" element={ <Home/> }/>
        <Route path="/next" element={ <Page1/> }/>

        <Route path="/note" element={ <Simple_notebook/> }/>
        <Route path="/notebooks/:title" element={<NotebookDetail />} />

        <Route path="/notebooks" element = { <PageNotebooks/> }/>
        <Route path="/signUpPage" element = { <SignUpPage/> }/>
        <Route path="/profile/:username" element = { <Profile/> }/>
        <Route path="/verify" element = { <VerifyEmail/> }/>
        <Route path="/forgot_login" element = { <Forgot_login/> }/>
        <Route path="/reset_password" element = { <Reset_Password/> }/>
        {/* Test Pages */}
        <Route path="/notebooks/:groupID/:pageNum" element = { <ToolTest/> }/>
        <Route path="/test/pagewrite" element = { <TestPageWrite/> }/>
      </Routes>
    </Router>
  )

}



export default App;
